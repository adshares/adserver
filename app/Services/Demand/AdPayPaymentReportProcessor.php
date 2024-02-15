<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types=1);

namespace Adshares\Adserver\Services\Demand;

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Conversion;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\EventCreditLog;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\User;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Exception\RuntimeException;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use stdClass;

class AdPayPaymentReportProcessor
{
    public const STATUS_PAYMENT_ACCEPTED = 0;

    private float $exchangeRateValue;
    private array $advertisers = [];
    private array $conversionDefinitions = [];

    public function __construct(private readonly ExchangeRate $exchangeRate)
    {
        $this->exchangeRateValue = $exchangeRate->getValue();
    }

    public function processEventLog(stdClass $event, array $calculation): array
    {
        if (!isset($calculation['status'])) {
            throw new RuntimeException('Missing event status');
        }

        if (self::STATUS_PAYMENT_ACCEPTED !== ($status = $calculation['status'])) {
            return $this->getEventStatus($status);
        }

        if (!isset($calculation['value'])) {
            throw new RuntimeException('Missing event value');
        }
        if (0 > ($value = $calculation['value'])) {
            throw new RuntimeException('Invalid event value');
        }

        $advertiserPublicId = $event->advertiser_id;

        if (!$this->isUser($advertiserPublicId)) {
            Log::warning(sprintf('No user with uuid (%s)', $advertiserPublicId));

            return $this->getEventValueAndStatus(0, 0, $status);
        }

        $campaignPublicId = $event->campaign_id;

        if (!$this->isCampaign($advertiserPublicId, $campaignPublicId)) {
            Log::warning(sprintf('No campaign with uuid (%s)', $campaignPublicId));

            return $this->getEventValueAndStatus(0, 0, $status);
        }

        $isDirectDeal = $this->advertisers[$advertiserPublicId]['campaigns'][$campaignPublicId]['isDirectDeal'];
        $budget = $this->advertisers[$advertiserPublicId]['campaigns'][$campaignPublicId]['budgetLeft'];
        $wallet = $this->advertisers[$advertiserPublicId]['walletLeft'];
        $bonus = $this->advertisers[$advertiserPublicId]['bonusLeft'];

        $maxAvailableValue = (int)min($budget, $isDirectDeal ? $wallet : $wallet + $bonus);

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

        return $this->getEventValueAndStatus($value, $this->exchangeRate->toClick($value), $status);
    }

    public function processConversion(Conversion $conversion, array $calculation): void
    {
        if (!isset($calculation['status'])) {
            throw new RuntimeException('Missing conversion status');
        }

        if (self::STATUS_PAYMENT_ACCEPTED !== ($status = $calculation['status'])) {
            $conversion->setStatus($status);

            return;
        }

        if (!isset($calculation['value'])) {
            throw new RuntimeException('Missing conversion value');
        }
        if (0 > ($value = $calculation['value'])) {
            throw new RuntimeException('Invalid conversion value');
        }

        if (!$conversion->event) {
            Log::warning(sprintf('Cannot find conversion event'));
            $conversion->setValueAndStatus(0, $this->exchangeRateValue, 0, $status);
            return;
        }

        $advertiserPublicId = $conversion->event->advertiser_id;

        if (!$this->isUser($advertiserPublicId)) {
            Log::warning(sprintf('No user with uuid (%s)', $advertiserPublicId));
            $conversion->setValueAndStatus(0, $this->exchangeRateValue, 0, $status);

            return;
        }

        $campaignPublicId = $conversion->event->campaign_id;

        if (!$this->isCampaign($advertiserPublicId, $campaignPublicId)) {
            Log::warning(sprintf('No campaign with uuid (%s)', $campaignPublicId));
            $conversion->setValueAndStatus(0, $this->exchangeRateValue, 0, $status);

            return;
        }

        $definitionId = $conversion->conversion_definition_id;

        if (!$this->isConversionDefinition($definitionId)) {
            Log::warning(sprintf('No conversions definitions with id (%s)', $definitionId));
            $conversion->setValueAndStatus(0, $this->exchangeRateValue, 0, $status);

            return;
        }

        $campaignBudget = $this->advertisers[$advertiserPublicId]['campaigns'][$campaignPublicId]['budgetLeft'];
        $wallet = $this->advertisers[$advertiserPublicId]['walletLeft'];
        $conversionIsInCampaignBudget = $this->conversionDefinitions[$definitionId]['isInCampaignBudget'];

        $maxAvailableValue = $conversionIsInCampaignBudget ? (int)min($wallet, $campaignBudget) : $wallet;
        $value = $calculation['value'];

        if ($value > $maxAvailableValue) {
            $value = $maxAvailableValue;
        }

        if ($conversionIsInCampaignBudget) {
            $this->advertisers[$advertiserPublicId]['campaigns'][$campaignPublicId]['budgetLeft'] =
                $campaignBudget - $value;
        }

        $this->advertisers[$advertiserPublicId]['walletLeft'] = $wallet - $value;

        $this->conversionDefinitions[$definitionId]['cost'] += $value;
        $this->conversionDefinitions[$definitionId]['occurrences']++;

        $conversion->setValueAndStatus($value, $this->exchangeRateValue, $this->exchangeRate->toClick($value), $status);
    }

    private function isUser(string $advertiserPublicId): bool
    {
        if (!isset($this->advertisers[$advertiserPublicId])) {
            $user = User::fetchByUuid($advertiserPublicId);

            if (!$user) {
                return false;
            }

            $this->initAdvertiser($advertiserPublicId, $user);
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

            $this->initCampaign($advertiserPublicId, $campaign);
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
                'isInCampaignBudget' => $definition->isInCampaignBudget(),
                'cost' => $cost,
                'occurrences' => $definition->occurrences,
            ];
        }

        return true;
    }

    private function getEventStatus(int $status): array
    {
        return ['payment_status' => $status];
    }

    private function getEventValueAndStatus(int $valueCurrency, int $value, int $status): array
    {
        return [
            'event_value_currency' => $valueCurrency,
            'exchange_rate' => $this->exchangeRateValue,
            'event_value' => $value,
            'payment_status' => $status,
        ];
    }

    public function getAdvertiserExpenses(): array
    {
        $expenses = [];

        foreach ($this->advertisers as $advertiserData) {
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

    public function allocateCampaignExperimentBudgets(DateTimeInterface $dateTime): void
    {
        $campaigns = Campaign::fetchActiveCampaigns($dateTime)
//            ->filter(fn (Campaign $campaign) => $campaign->getEffectiveExperimentBudget() > 0);
            ->groupBy('user_id')
        ;

        // TODO get SSPs and assign wages to them
        $sspHosts = [];
        $hosts = NetworkHost::fetchHosts();
        foreach ($hosts as $host) {
            $sspHosts[] = [
                'host' => $host->address,
                'weight' => 1 / count($hosts),
            ];
        }

        foreach ($campaigns as $userId => $userCampaigns) {
            foreach ($userCampaigns as $campaign) {
                $experimentBudget = $campaign->getEffectiveExperimentBudget();
                if ($experimentBudget <= 0) {
                    continue;
                }

                if (null === $advertiser = User::fetchById($userId)) {
                    continue;
                }

                $this->initAdvertiser($advertiser->uuid, $advertiser);
                $this->initCampaign($advertiser->uuid, $campaign);

                $isDirectDeal = $this->advertisers[$advertiser->uuid]['campaigns'][$campaign->uuid]['isDirectDeal'];
                $wallet = $this->advertisers[$advertiser->uuid]['walletLeft'];
                $bonus = $this->advertisers[$advertiser->uuid]['bonusLeft'];

                $value = (int)min($experimentBudget, $isDirectDeal ? $wallet : $wallet + $bonus);

                if ($isDirectDeal) {
                    $this->advertisers[$advertiser->uuid]['walletLeft'] = $wallet - $value;
                } else {
                    if ($value > $bonus) {
                        $this->advertisers[$advertiser->uuid]['bonusLeft'] = 0;
                        $this->advertisers[$advertiser->uuid]['walletLeft'] = $wallet - ($value - $bonus);
                    } else {
                        $this->advertisers[$advertiser->uuid]['bonusLeft'] = $bonus - $value;
                    }
                }

                foreach ($sspHosts as $ssp) {
                    $eventValueInCurrency = (int)floor($value * $ssp['weight']);
                    $eventValue = $this->exchangeRate->toClick($eventValueInCurrency);
                    EventCreditLog::create(
                        $dateTime,
                        $advertiser->uuid,
                        $campaign->uuid,
                        $ssp['host'],
                        $eventValueInCurrency,
                        $this->exchangeRateValue,
                        $eventValue,
                        0,
                        0,
                        0,
                        $eventValue,
                    );
                }
            }
        }
    }

    private function initAdvertiser(string $advertiserPublicId, User $user): void
    {
        if (isset($this->advertisers[$advertiserPublicId])) {
            return;
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

    private function initCampaign(string $advertiserPublicId, Campaign $campaign): void
    {
        if (isset($this->advertisers[$advertiserPublicId]['campaigns'][$campaign->uuid])) {
            return;
        }

        $this->advertisers[$advertiserPublicId]['campaigns'][$campaign->uuid] = [
            'isDirectDeal' => $campaign->isDirectDeal(),
            'budget' => $campaign->budget,
            'budgetLeft' => $campaign->budget,
        ];
    }
}
