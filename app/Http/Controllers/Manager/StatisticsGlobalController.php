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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Models\TurnoverEntry;
use Adshares\Adserver\Repository\Common\TotalFeeReader;
use Adshares\Adserver\Repository\Demand\MySqlDemandServerStatisticsRepository;
use Adshares\Adserver\Repository\Supply\MySqlSupplyServerStatisticsRepository;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\ValueObject\TurnoverEntryType;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class StatisticsGlobalController extends Controller
{
    private const DEMAND_EXPENSE_TYPES = [
        TurnoverEntryType::DspExpense,
        TurnoverEntryType::DspJoiningFeeAllocation,
    ];
    private const SUPPLY_EXPENSE_TYPES = [
        TurnoverEntryType::SspJoiningFeeExpense,
    ];
    private const SUPPLY_OPERATOR_INCOME_TYPES = [
        TurnoverEntryType::SspBoostOperatorIncome,
        TurnoverEntryType::SspOperatorFee,
    ];
    private const SUPPLY_PUBLISHERS_INCOME_TYPES = [
        TurnoverEntryType::SspBoostLocked,
        TurnoverEntryType::SspPublishersIncome,
    ];

    public function __construct(
        private readonly ConfigurationRepository $configurationRepository,
        private readonly MySqlDemandServerStatisticsRepository $demandRepository,
        private readonly MySqlSupplyServerStatisticsRepository $supplyRepository,
        private readonly TotalFeeReader $totalFeeReader,
    ) {
    }

    public function fetchDemandStatistics(): array
    {
        return $this->demandRepository->fetchStatistics();
    }

    public function fetchDemandDomains(Request $request): array
    {
        $days = max(1, min(30, (int)$request->get('days', 30)));
        $offset = max(0, min(30 - $days, (int)$request->get('offset', 0)));

        return $this->demandRepository->fetchDomains($days, $offset);
    }

    public function fetchDemandCampaigns(): array
    {
        return $this->demandRepository->fetchCampaigns();
    }

    public function fetchDemandBannersSizes(): array
    {
        return $this->demandRepository->fetchBannersSizes();
    }

    public function fetchDemandBannersTypes(): array
    {
        $data = [];

        $taxonomy = $this->configurationRepository->fetchTaxonomy();
        foreach ($taxonomy->getMedia() as $medium) {
            $types = [];
            foreach ($medium->getFormats() as $format) {
                $formatType = $format->getType();
                if (in_array($formatType, [Banner::TYPE_HTML, Banner::TYPE_IMAGE, Banner::TYPE_VIDEO], true)) {
                    $types[] = 'display';
                } elseif (Banner::TYPE_DIRECT_LINK === $formatType) {
                    $scopes = array_keys($format->getScopes());
                    if (!empty(array_intersect($scopes, ['pop-under', 'pop-up']))) {
                        $types[] = 'pop';
                    }
                    if (!empty(array_diff($scopes, ['pop-under', 'pop-up']))) {
                        $types[] = 'smart-link';
                    }
                } elseif (Banner::TYPE_MODEL === $formatType) {
                    $types[] = 'model';
                }
            }
            $types = array_values(array_unique($types));
            $data[] = [
                'medium' => $medium->getLabel(),
                'vendor' => $medium->getVendorLabel(),
                'types' => $types,
            ];
        }

        return $data;
    }

    public function fetchDemandTurnover(string $from, string $to): array
    {
        $from = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $from);
        $to = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $to);
        if (false === $from || false === $to) {
            throw new UnprocessableEntityHttpException('Invalid date format');
        }
        $minDate = TurnoverEntry::fetchFirstJoiningFee()?->hour_timestamp;
        if (null === $minDate || $to < $minDate) {
            throw new NotFoundHttpException('No entry');
        }

        $turnoverEntries = TurnoverEntry::fetchByHourTimestamp($from, $to);
        $expense = 0;
        foreach ($turnoverEntries as $entry) {
            if (in_array($entry->type, self::DEMAND_EXPENSE_TYPES, true)) {
                $expense += $entry->amount;
            }
        }

        return compact('expense');
    }

    public function fetchSupplyStatistics(): array
    {
        $totalFee = $this->totalFeeReader->getTotalFeeSupply();

        return $this->supplyRepository->fetchStatistics($totalFee);
    }

    public function fetchSupplyDomains(Request $request): array
    {
        $days = max(1, min(30, (int)$request->get('days', 30)));
        $offset = max(0, min(30 - $days, (int)$request->get('offset', 0)));

        $totalFee = $this->totalFeeReader->getTotalFeeSupply();

        return $this->supplyRepository->fetchDomains($totalFee, $days, $offset);
    }

    public function fetchSupplyTurnover(string $from, string $to): array
    {
        $from = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $from);
        $to = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $to);
        if (false === $from || false === $to) {
            throw new UnprocessableEntityHttpException('Invalid date format');
        }
        $minDate = TurnoverEntry::fetchFirstJoiningFee()?->hour_timestamp;
        if (null === $minDate || $to < $minDate) {
            throw new NotFoundHttpException('No entry');
        }

        $turnoverEntries = TurnoverEntry::fetchByHourTimestamp($from, $to);
        $expense = 0;
        $operatorIncome = 0;
        $publishersIncome = 0;
        foreach ($turnoverEntries as $entry) {
            if (in_array($entry->type, self::SUPPLY_EXPENSE_TYPES, true)) {
                $expense += $entry->amount;
            }
            if (in_array($entry->type, self::SUPPLY_OPERATOR_INCOME_TYPES, true)) {
                $operatorIncome += $entry->amount;
            }
            if (in_array($entry->type, self::SUPPLY_PUBLISHERS_INCOME_TYPES, true)) {
                $publishersIncome += $entry->amount;
            }
        }

        return compact('expense', 'operatorIncome', 'publishersIncome');
    }

    public function fetchSupplyZonesSizes(): array
    {
        return $this->supplyRepository->fetchZonesSizes();
    }

    public function fetchServerStatisticsAsFile(string $date): StreamedResponse
    {
        if (1 !== preg_match('/^[0-9]{8}$/', $date)) {
            throw new InvalidArgumentException('Date must be passed in Ymd format e.g. 20210328');
        }

        $file = $date . '_statistics.csv';

        if (!Storage::disk('public')->exists($file)) {
            throw new NotFoundHttpException('No statistics for date ' . $date);
        }

        return Storage::disk('public')->download($file);
    }
}
