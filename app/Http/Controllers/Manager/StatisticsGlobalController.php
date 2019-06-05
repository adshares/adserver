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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Repository\Demand\MySqlDemandServerStatisticsRepository;
use Adshares\Adserver\Repository\Supply\MySqlSupplyServerStatisticsRepository;
use Adshares\Common\Infrastructure\Service\LicenseReader;

class StatisticsGlobalController extends Controller
{
    /** @var LicenseReader */
    private $licenseReader;

    /** @var MySqlDemandServerStatisticsRepository */
    private $demandRepository;

    /** @var MySqlSupplyServerStatisticsRepository */
    private $supplyRepository;

    public function __construct(
        LicenseReader $licenseReader,
        MySqlDemandServerStatisticsRepository $demandRepository,
        MySqlSupplyServerStatisticsRepository $supplyRepository
    ) {
        $this->licenseReader = $licenseReader;
        $this->demandRepository = $demandRepository;
        $this->supplyRepository = $supplyRepository;
    }

    public function fetchDemandStatistics()
    {
        return $this->demandRepository->fetchStatistics();
    }

    public function fetchDemandDomains()
    {
        return $this->demandRepository->fetchDomains();
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
        $operatorFee = Config::fetchFloatOrFail(Config::OPERATOR_RX_FEE);
        $licenseFee = $this->licenseReader->getFee(Config::LICENCE_RX_FEE);

        return $this->supplyRepository->fetchStatistics($operatorFee, $licenseFee);
    }

    public function fetchSupplyZonesSizes()
    {
        return $this->supplyRepository->fetchZonesSizes();
    }
}
