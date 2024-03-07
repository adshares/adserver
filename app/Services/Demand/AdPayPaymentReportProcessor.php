<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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
use Adshares\Adserver\Models\JoiningFee;
use Adshares\Adserver\Models\JoiningFeeLog;
use Adshares\Adserver\Models\SspHost;
use Adshares\Adserver\Models\TurnoverEntry;
use Adshares\Adserver\Models\User;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Exception\RuntimeException;
use DateTimeImmutable;
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

        $isDirectDeal = $this->getIsDirectDeal($advertiserPublicId, $campaignPublicId);
        $budget = $this->getBudgetLeft($advertiserPublicId, $campaignPublicId);
        $wallet = $this->getWalletLeft($advertiserPublicId);
        $bonus = $this->getBonusLeft($advertiserPublicId);

        $maxAvailableValue = (int)min($budget, $isDirectDeal ? $wallet : $wallet + $bonus);
        if ($value > $maxAvailableValue) {
            $value = $maxAvailableValue;
        }

        $this->advertisers[$advertiserPublicId]['campaigns'][$campaignPublicId]['budgetLeft'] = $budget - $value;
        $this->chargeEventValue($advertiserPublicId, $value, $wallet, $bonus, $isDirectDeal);

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
            Log::warning('Cannot find conversion event');
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

        $campaignBudget = $this->getBudgetLeft($advertiserPublicId, $campaignPublicId);
        $wallet = $this->getWalletLeft($advertiserPublicId);
        $conversionIsInCampaignBudget = $this->conversionDefinitions[$definitionId]['isInCampaignBudget'];

        $maxAvailableValue = $conversionIsInCampaignBudget ? (int)min($wallet, $campaignBudget) : $wallet;
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

    public function allocateCampaignExperimentBudgets(DateTimeInterface $computationDateTime): void
    {
        $campaigns = Campaign::fetchActiveCampaigns($computationDateTime)
            ->groupBy('user_id');

        $sspHosts = $this->getSspHosts();

        foreach ($campaigns as $userId => $userCampaigns) {
            /** @var Campaign $campaign */
            foreach ($userCampaigns as $campaign) {
                $experimentBudget = $campaign->getEffectiveExperimentBudget();
                if ($experimentBudget <= 0) {
                    continue;
                }

                if (null === $advertiser = User::fetchById($userId)) {
                    continue;
                }

                $advertiserPublicId = $advertiser->uuid;
                $this->initAdvertiser($advertiserPublicId, $advertiser);
                $this->initCampaign($advertiserPublicId, $campaign);

                $isDirectDeal = $this->getIsDirectDeal($advertiserPublicId, $campaign->uuid);
                $wallet = $this->getWalletLeft($advertiserPublicId);
                $bonus = $this->getBonusLeft($advertiserPublicId);

                $value = (int)min($experimentBudget, $isDirectDeal ? $wallet : $wallet + $bonus);

                $this->chargeEventValue($advertiserPublicId, $value, $wallet, $bonus, $isDirectDeal);

                $this->splitExperimentBudgetBetweenHosts(
                    $sspHosts,
                    $value,
                    $computationDateTime,
                    $advertiserPublicId,
                    $campaign->uuid,
                );
            }
        }

        $this->splitAllocationAmountBetweenHosts($sspHosts, $computationDateTime);
    }

    private function getSspHosts(): array
    {
        $adsAddresses = SspHost::fetchAccepted()
            ->map(fn(SspHost $item) => $item->ads_address)
            ->toArray();
        $from = new DateTimeImmutable('-30 days');
        $conversions = Conversion::fetchPaidConversionsByPayTo($from, $adsAddresses)
            ->keyBy('pay_to');
        $creditLogs = EventCreditLog::fetchByPayTo($from, $adsAddresses)
            ->keyBy('pay_to');
        $joiningFeesByAdsAddress = JoiningFee::fetchJoiningFeesForAllocation()
            ->groupBy('ads_address');

        $totalReputation = 0;
        $sspHosts = [];
        foreach ($adsAddresses as $adsAddress) {
            $reputation = TurnoverEntry::getJoiningFeeIncome($adsAddress)
                + (int)($conversions->get($adsAddress)?->value ?? 0)
                - (int)($creditLogs->get($adsAddress)?->value ?? 0);
            $totalReputation += $reputation;

            $allocationAmount = 0;
            if (null !== $joiningFees = $joiningFeesByAdsAddress->get($adsAddress)) {
                /** @var JoiningFee $joiningFee */
                foreach ($joiningFees as $joiningFee) {
                    $allocationAmountPart = $joiningFee->getAllocationAmount();
                    $joiningFee->left_amount -= $allocationAmountPart;
                    $joiningFee->save();
                    if ($joiningFee->left_amount < config('app.joining_fee_allocation_min')) {
                        $joiningFee->delete();
                    }
                    $allocationAmount += $allocationAmountPart;
                }
            }

            $sspHosts[] = [
                'adsAddress' => $adsAddress,
                'allocationAmount' => $allocationAmount,
                'reputation' => $reputation,
            ];
        }

        foreach ($sspHosts as &$sspHost) {
            $sspHost['weight'] = $sspHost['reputation'] / $totalReputation;
        }

        return $sspHosts;
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

    private function getWalletLeft(string $uuid): int
    {
        return $this->advertisers[$uuid]['walletLeft'];
    }

    private function getBonusLeft(string $uuid): int
    {
        return $this->advertisers[$uuid]['bonusLeft'];
    }

    private function getIsDirectDeal(string $advertiserPublicId, string $campaignPublicId): bool
    {
        return $this->advertisers[$advertiserPublicId]['campaigns'][$campaignPublicId]['isDirectDeal'];
    }

    private function getBudgetLeft(string $advertiserPublicId, string $campaignPublicId): mixed
    {
        return $this->advertisers[$advertiserPublicId]['campaigns'][$campaignPublicId]['budgetLeft'];
    }

    private function chargeEventValue(
        string $advertiserPublicId,
        int $fee,
        int $wallet,
        int $bonus,
        bool $isDirectDeal,
    ): void {
        if ($isDirectDeal) {
            $this->advertisers[$advertiserPublicId]['walletLeft'] = $wallet - $fee;
        } else {
            if ($fee > $bonus) {
                $this->advertisers[$advertiserPublicId]['bonusLeft'] = 0;
                $this->advertisers[$advertiserPublicId]['walletLeft'] = $wallet - ($fee - $bonus);
            } else {
                $this->advertisers[$advertiserPublicId]['bonusLeft'] = $bonus - $fee;
            }
        }
    }

    private function splitExperimentBudgetBetweenHosts(
        array $sspHosts,
        int $value,
        DateTimeInterface $computationDateTime,
        string $advertiserPublicId,
        string $campaignPublicId,
    ): void {
        foreach ($sspHosts as $ssp) {
            $eventValueInCurrency = (int)floor($value * $ssp['weight']);
            EventCreditLog::create(
                $computationDateTime,
                $advertiserPublicId,
                $campaignPublicId,
                $ssp['adsAddress'],
                $eventValueInCurrency,
                $this->exchangeRateValue,
                $this->exchangeRate->toClick($eventValueInCurrency),
            );
        }
    }

    private function splitAllocationAmountBetweenHosts(array $sspHosts, DateTimeInterface $computationDateTime): void
    {
        $totalAllocationAmount = array_reduce(
            $sspHosts,
            fn(int $carry, array $sspHost) => $carry + $sspHost['allocationAmount'],
            0,
        );
        $totalAllocationAmount = (int)floor($totalAllocationAmount / 2);

        foreach ($sspHosts as $sspHost) {
            $allocationAmount =
                (int)floor($sspHost['allocationAmount'] / 2)
                + (int)floor($totalAllocationAmount * $sspHost['weight']);
            if ($allocationAmount > 0) {
                JoiningFeeLog::create(
                    $computationDateTime,
                    $sspHost['adsAddress'],
                    $allocationAmount,
                );
            }
        }
    }
}
