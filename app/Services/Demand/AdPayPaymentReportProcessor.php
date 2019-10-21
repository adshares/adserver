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
        $advertiserId = $event->advertiser_id;

        if (!$this->isUser($advertiserId)) {
            Log::warning(sprintf('No user with uuid (%s)', $advertiserId));
            $this->setEventValueAndStatus($event, 0, 0, $calculation['status']);

            return;
        }

        $campaignId = $event->campaign_id;

        if (!$this->isCampaign($advertiserId, $campaignId)) {
            Log::warning(sprintf('No campaign with uuid (%s)', $campaignId));
            $this->setEventValueAndStatus($event, 0, 0, $calculation['status']);

            return;
        }

        $isDirectDeal = $this->advertisers[$advertiserId]['campaigns'][$campaignId]['isDirectDeal'];
        $budget = $this->advertisers[$advertiserId]['campaigns'][$campaignId]['budgetLeft'];
        $wallet = $this->advertisers[$advertiserId]['walletLeft'];
        $bonus = $this->advertisers[$advertiserId]['bonusLeft'];

        $maxAvailableValue = (int)min($budget, $isDirectDeal ? $wallet : $wallet + $bonus);
        $value = $calculation['value'];

        if ($value > $maxAvailableValue) {
            $value = $maxAvailableValue;
        }

        $this->advertisers[$advertiserId]['campaigns'][$campaignId]['budgetLeft'] = $budget - $value;

        if ($isDirectDeal) {
            $this->advertisers[$advertiserId]['walletLeft'] = $wallet - $value;
        } else {
            if ($value > $bonus) {
                $this->advertisers[$advertiserId]['bonusLeft'] = 0;
                $this->advertisers[$advertiserId]['walletLeft'] = $wallet - ($value - $bonus);
            } else {
                $this->advertisers[$advertiserId]['bonusLeft'] = $bonus - $value;
            }
        }

        $this->setEventValueAndStatus($event, $value, $this->exchangeRate->toClick($value), $calculation['status']);
    }

    public function processConversion(Conversion $conversion, array $calculation): void
    {
        $advertiserId = $conversion->event->advertiser_id;

        if (!$this->isUser($advertiserId)) {
            Log::warning(sprintf('No user with uuid (%s)', $advertiserId));
            $this->setConversionValueAndStatus($conversion, 0, 0, $calculation['status']);

            return;
        }

        $campaignId = $conversion->event->campaign_id;

        if (!$this->isCampaign($advertiserId, $campaignId)) {
            Log::warning(sprintf('No campaign with uuid (%s)', $campaignId));
            $this->setConversionValueAndStatus($conversion, 0, 0, $calculation['status']);

            return;
        }

        $definitionId = $conversion->conversion_definition_id;

        if (!$this->isConversionDefinition($definitionId)) {
            Log::warning(sprintf('No conversions definitions with id (%s)', $definitionId));
            $this->setConversionValueAndStatus($conversion, 0, 0, $calculation['status']);

            return;
        }

        $campaignBudget = $this->advertisers[$advertiserId]['campaigns'][$campaignId]['budgetLeft'];
        $wallet = $this->advertisers[$advertiserId]['walletLeft'];
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
            $this->advertisers[$advertiserId]['campaigns'][$campaignId]['budgetLeft'] = $campaignBudget - $value;
        }

        $this->advertisers[$advertiserId]['walletLeft'] = $wallet - $value;
        $this->conversionDefinitions[$definitionId]['limitLeft'] = $conversionLimit - $value;

        $this->conversionDefinitions[$definitionId]['cost'] += $value;
        $this->conversionDefinitions[$definitionId]['occurrences']++;

        $this->setConversionValueAndStatus(
            $conversion,
            $value,
            $this->exchangeRate->toClick($value),
            $calculation['status']
        );
    }

    private function isUser(string $advertiserId): bool
    {
        if (!isset($this->advertisers[$advertiserId])) {
            $user = User::fetchByUuid($advertiserId);

            if (!$user) {
                return false;
            }

            $walletBalance = $this->exchangeRate->fromClick($user->getWalletBalance());
            $bonusBalance = $this->exchangeRate->fromClick($user->getBonusBalance());

            $this->advertisers[$advertiserId] = [
                'wallet' => $walletBalance,
                'walletLeft' => $walletBalance,
                'bonus' => $bonusBalance,
                'bonusLeft' => $bonusBalance,
                'campaigns' => [],
            ];
        }

        return true;
    }

    private function isCampaign(string $advertiserId, string $campaignId): bool
    {
        if (!isset($this->advertisers[$advertiserId]['campaigns'][$campaignId])) {
            $campaign = Campaign::fetchByUuid($campaignId);

            if (!$campaign) {
                return false;
            }

            $this->advertisers[$advertiserId]['campaigns'][$campaignId] = [
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

    private function setEventValueAndStatus(EventLog $event, int $valueCurrency, int $value, int $status): void
    {
        $event->event_value_currency = $valueCurrency;
        $event->exchange_rate = $this->exchangeRateValue;
        $event->event_value = $value;
        $event->payment_status = $status;

        $event->save();
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

        foreach ($this->advertisers as $advertiserId => $balances) {
            $wallet = $this->exchangeRate->toClick($balances['wallet'] - $balances['walletLeft']);
            $bonus = $this->exchangeRate->toClick($balances['bonus'] - $balances['bonusLeft']);

            if (0 === ($total = $wallet + $bonus)) {
                continue;
            }

            $expenses[$advertiserId] = [
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
