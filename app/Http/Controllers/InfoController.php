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

namespace Adshares\Adserver\Http\Controllers;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Response\InfoResponse;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Regulation;
use Adshares\Adserver\Repository\Common\MySqlServerStatisticsRepository;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Illuminate\View\View;

class InfoController extends Controller
{
    private const FEE_PRECISION_MAXIMUM = 4;

    /** @var LicenseReader */
    private $licenseReader;

    /** @var MySqlServerStatisticsRepository */
    private $adserverStatisticsRepository;

    public function __construct(
        LicenseReader $licenseReader,
        MySqlServerStatisticsRepository $adserverStatisticsRepository
    ) {
        $this->licenseReader = $licenseReader;
        $this->adserverStatisticsRepository = $adserverStatisticsRepository;
    }

    public function info(): InfoResponse
    {
        $response = InfoResponse::defaults();

        $statistics = $this->adserverStatisticsRepository->fetchInfoStatistics();
        $response->updateWithStatistics($statistics);

        $licenseTxFee = $this->licenseReader->getFee(Config::LICENCE_TX_FEE);
        $operatorTxFee = Config::fetchFloatOrFail(Config::OPERATOR_TX_FEE);
        $response->updateWithDemandFee($this->calculateTotalFee($licenseTxFee, $operatorTxFee));

        $licenseRxFee = $this->licenseReader->getFee(Config::LICENCE_RX_FEE);
        $operatorRxFee = Config::fetchFloatOrFail(Config::OPERATOR_RX_FEE);
        $response->updateWithSupplyFee($this->calculateTotalFee($licenseRxFee, $operatorRxFee));

        return $response;
    }

    private function calculateTotalFee(float $licenseFee, float $operatorFee): float
    {
        return round($licenseFee + (1 - $licenseFee) * $operatorFee, self::FEE_PRECISION_MAXIMUM);
    }

    public function privacyPolicy(): View
    {
        $data = Regulation::fetchPrivacyPolicy()->toArray();

        return view('info/policy', $data);
    }

    public function terms(): View
    {
        $data = Regulation::fetchTerms()->toArray();

        return view('info/policy', $data);
    }
}
