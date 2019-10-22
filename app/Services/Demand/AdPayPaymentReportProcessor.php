<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types = 1);

namespace Adshares\Adserver\Services\Demand;

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Conversion;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\User;
use Adshares\Common\Application\Dto\ExchangeRate;
use Illuminate\Support\Facades\Log;

class AdPayPaymentReportProcessor
{
    /** @var ExchangeRate */
    private $exchangeRate;

    /** @var float */
    private $exchangeRateValue;

    /** @var array */
    private $advertisers = [];

    /** @var array */
    private $conversionDefinitions = [];

    public function __construct(ExchangeRate $exchangeRate)
    {
        $this->exchangeRate = $exchangeRate;
        $this->exchangeRateValue = $exchangeRate->getValue();
    }

    public function processEventLog(EventLog $event, array $calculation): void
    {
        if (0 !== ($status = $calculation['status'])) {
            $this->setEventStatus($event, $status);

            return;
        }

        $advertiserPublicId = $event->advertiser_id;

        if (!$this->isUser($advertiserPublicId)) {
            Log::warning(sprintf('No user with uuid (%s)', $advertiserPublicId));
            $this->setEventValueAndStatus($event, 0, 0, $status);

            return;
        }

        $campaignPublicId = $event->campaign_id;

        if (!$this->isCampaign($advertiserPublicId, $campaignPublicId)) {
            Log::warning(sprintf('No campaign with uuid (%s)', $campaignPublicId));
            $this->setEventValueAndStatus($event, 0, 0, $status);

            return;
        }

        $isDirectDeal = $this->advertisers[$advertiserPublicId]['campaigns'][$campaignPublicId]['isDirectDeal'];
        $budget = $this->advertisers[$advertiserPublicId]['campaigns'][$campaignPublicId]['budgetLeft'];
        $wallet = $this->advertisers[$advertiserPublicId]['walletLeft'];
        $bonus = $this->advertisers[$advertiserPublicId]['bonusLeft'];

        $maxAvailableValue = (int)min($budget, $isDirectDeal ? $wallet : $wallet + $bonus);
        $value = $calculation['value'];

        if ($value > $maxAvailableValue) {
            $value = $maxAvailableValue;
        }

        $this->advertisers[$advertiserPublicId]['campaigns'][$campaignPublicId]['budgetLeft'] = $budget - $value;

        if ($isDirectDeal) {
            $this->advertisers[$advertiserPublicId]['walletLeft'] = $wallet - $value;
        } else {
            if ($value > $bonus) {
                $this->advertisers[$advertiserPublicId]['bonusLeft'] = 0;
                $this->advertisers[$advertiserPublicId]['walletLeft'] = $wallet - ($value - $bonus);
            } else {
                $this->advertisers[$advertiserPublicId]['bonusLeft'] = $bonus - $value;
            }
        }

        $this->setEventValueAndStatus($event, $value, $this->exchangeRate->toClick($value), $status);
    }

    public function processConversion(Conversion $conversion, array $calculation): void
    {
        if (0 !== ($status = $calculation['status'])) {
            $this->setConversionStatus($conversion, $status);

            return;
        }

        $advertiserPublicId = $conversion->event->advertiser_id;

        if (!$this->isUser($advertiserPublicId)) {
            Log::warning(sprintf('No user with uuid (%s)', $advertiserPublicId));
            $this->setConversionValueAndStatus($conversion, 0, 0, $status);

            return;
        }

        $campaignPublicId = $conversion->event->campaign_id;

        if (!$this->isCampaign($advertiserPublicId, $campaignPublicId)) {
            Log::warning(sprintf('No campaign with uuid (%s)', $campaignPublicId));
            $this->setConversionValueAndStatus($conversion, 0, 0, $status);

            return;
        }

        $definitionId = $conversion->conversion_definition_id;

        if (!$this->isConversionDefinition($definitionId)) {
            Log::warning(sprintf('No conversions definitions with id (%s)', $definitionId));
            $this->setConversionValueAndStatus($conversion, 0, 0, $status);

            return;
        }

        $campaignBudget = $this->advertisers[$advertiserPublicId]['campaigns'][$campaignPublicId]['budgetLeft'];
        $wallet = $this->advertisers[$advertiserPublicId]['walletLeft'];
        $conversionLimit = $this->conversionDefinitions[$definitionId]['limitLeft'];
        $conversionIsInCampaignBudget = $this->conversionDefinitions[$definitionId]['isInCampaignBudget'];

        $maxAvailableValue = $conversionIsInCampaignBudget
            ? (int)min($wallet, $conversionLimit, $campaignBudget)
            : (int)min(
                $wallet,
                $conversionLimit
            );
        $value = $calculation['value'];

        if ($value > $maxAvailableValue) {
            $value = $maxAvailableValue;
        }

        if ($conversionIsInCampaignBudget) {
            $this->advertisers[$advertiserPublicId]['campaigns'][$campaignPublicId]['budgetLeft'] =
                $campaignBudget - $value;
        }

        $this->advertisers[$advertiserPublicId]['walletLeft'] = $wallet - $value;
        $this->conversionDefinitions[$definitionId]['limitLeft'] = $conversionLimit - $value;

        $this->conversionDefinitions[$definitionId]['cost'] += $value;
        $this->conversionDefinitions[$definitionId]['occurrences']++;

        $this->setConversionValueAndStatus(
            $conversion,
            $value,
            $this->exchangeRate->toClick($value),
            $status
        );
    }

    private function isUser(string $advertiserPublicId): bool
    {
        if (!isset($this->advertisers[$advertiserPublicId])) {
            $user = User::fetchByUuid($advertiserPublicId);

            if (!$user) {
                return false;
            }

            $walletBalance = $this->exchangeRate->fromClick($user->getWalletBalance());
            $bonusBalance = $this->exchangeRate->fromClick($user->getBonusBalance());

            $this->advertisers[$advertiserPublicId] = [
                'id' => $user->id,
                'wallet' => $walletBalance,
                'walletLeft' => $walletBalance,
                'bonus' => $bonusBalance,
                'bonusLeft' => $bonusBalance,
                'campaigns' => [],
            ];
        }

        return true;
    }

    private function isCampaign(string $advertiserPublicId, string $campaignPublicId): bool
    {
        if (!isset($this->advertisers[$advertiserPublicId]['campaigns'][$campaignPublicId])) {
            $campaign = Campaign::fetchByUuid($campaignPublicId);

            if (!$campaign) {
                return false;
            }

            $this->advertisers[$advertiserPublicId]['campaigns'][$campaignPublicId] = [
                'isDirectDeal' => $campaign->isDirectDeal(),
                'budget' => $campaign->budget,
                'budgetLeft' => $campaign->budget,
            ];
        }

        return true;
    }

    private function isConversionDefinition(int $definitionId): bool
    {
        if (!isset($this->conversionDefinitions[$definitionId])) {
            $definition = ConversionDefinition::fetchById($definitionId);

            if (!$definition) {
                return false;
            }

            $cost = $definition->cost;

            $this->conversionDefinitions[$definitionId] = [
                'limitLeft' => $definition->limit - $cost,
                'isInCampaignBudget' => $definition->isInCampaignBudget(),
                'cost' => $cost,
                'occurrences' => $definition->occurrences,
            ];
        }

        return true;
    }

    private function setEventStatus(EventLog $event, int $status): void
    {
        $event->payment_status = $status;

        $event->save();
    }

    private function setEventValueAndStatus(EventLog $event, int $valueCurrency, int $value, int $status): void
    {
        $event->event_value_currency = $valueCurrency;
        $event->exchange_rate = $this->exchangeRateValue;
        $event->event_value = $value;
        $event->payment_status = $status;

        $event->save();
    }

    private function setConversionStatus(Conversion $conversion, int $status): void
    {
        $conversion->payment_status = $status;

        $conversion->save();
    }

    private function setConversionValueAndStatus(
        Conversion $conversion,
        int $valueCurrency,
        int $value,
        int $status
    ): void {
        $conversion->event_value_currency = $valueCurrency;
        $conversion->exchange_rate = $this->exchangeRateValue;
        $conversion->event_value = $value;
        $conversion->payment_status = $status;

        $conversion->save();
    }

    public function getAdvertiserExpenses(): array
    {
        $expenses = [];

        foreach ($this->advertisers as $advertiserId => $advertiserData) {
            $wallet = $this->exchangeRate->toClick($advertiserData['wallet'] - $advertiserData['walletLeft']);
            $bonus = $this->exchangeRate->toClick($advertiserData['bonus'] - $advertiserData['bonusLeft']);

            if (0 === ($total = $wallet + $bonus)) {
                continue;
            }

            $expenses[$advertiserData['id']] = [
                'total' => $total,
                'maxBonus' => $bonus,
            ];
        }

        return $expenses;
    }

    public function getProcessedConversionDefinitions(): array
    {
        $definitions = [];

        foreach ($this->conversionDefinitions as $id => $data) {
            $definitions[$id] = [
                'cost' => $data['cost'],
                'occurrences' => $data['occurrences'],
            ];
        }

        return $definitions;
    }
}
