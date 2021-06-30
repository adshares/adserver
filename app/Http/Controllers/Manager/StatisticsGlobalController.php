<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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
use Adshares\Adserver\Repository\Common\TotalFeeReader;
use Adshares\Adserver\Repository\Demand\MySqlDemandServerStatisticsRepository;
use Adshares\Adserver\Repository\Supply\MySqlSupplyServerStatisticsRepository;
use Adshares\Common\Exception\InvalidArgumentException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StatisticsGlobalController extends Controller
{
    /** @var MySqlDemandServerStatisticsRepository */
    private $demandRepository;

    /** @var MySqlSupplyServerStatisticsRepository */
    private $supplyRepository;

    /** @var TotalFeeReader */
    private $totalFeeReader;

    public function __construct(
        MySqlDemandServerStatisticsRepository $demandRepository,
        MySqlSupplyServerStatisticsRepository $supplyRepository,
        TotalFeeReader $totalFeeReader
    ) {
        $this->demandRepository = $demandRepository;
        $this->supplyRepository = $supplyRepository;
        $this->totalFeeReader = $totalFeeReader;
    }

    public function fetchDemandStatistics()
    {
        return $this->demandRepository->fetchStatistics();
    }

    public function fetchDemandDomains(Request $request)
    {
        $days = max(1, min(30, (int)$request->get('days', 30)));
        $offset = max(0, min(30 - $days, (int)$request->get('offset', 0)));

        return $this->demandRepository->fetchDomains($days, $offset);
    }

    public function fetchDemandCampaigns()
    {
        return $this->demandRepository->fetchCampaigns();
    }

    public function fetchDemandBannersSizes()
    {
        return $this->demandRepository->fetchBannersSizes();
    }

    public function fetchSupplyStatistics()
    {
        $totalFee = $this->totalFeeReader->getTotalFeeSupply();

        return $this->supplyRepository->fetchStatistics($totalFee);
    }

    public function fetchSupplyDomains(Request $request)
    {
        $days = max(1, min(30, (int)$request->get('days', 30)));
        $offset = max(0, min(30 - $days, (int)$request->get('offset', 0)));

        $totalFee = $this->totalFeeReader->getTotalFeeSupply();

        return $this->supplyRepository->fetchDomains($totalFee, $days, $offset);
    }

    public function fetchSupplyZonesSizes()
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
