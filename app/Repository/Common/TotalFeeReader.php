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

namespace Adshares\Adserver\Repository\Common;

use Adshares\Adserver\Models\Config;
use Adshares\Common\Infrastructure\Service\LicenseReader;

class TotalFeeReader
{
    private const FEE_PRECISION_MAXIMUM = 4;

    /** @var LicenseReader */
    private $licenseReader;

    public function __construct(LicenseReader $licenseReader)
    {
        $this->licenseReader = $licenseReader;
    }

    public function getTotalFeeDemand(): float
    {
        $licenseFee = $this->licenseReader->getFee(Config::LICENCE_TX_FEE);
        $operatorFee = Config::fetchFloatOrFail(Config::OPERATOR_TX_FEE);

        return $this->computeTotalFee($licenseFee, $operatorFee);
    }

    public function getTotalFeeSupply(): float
    {
        $licenseFee = $this->licenseReader->getFee(Config::LICENCE_RX_FEE);
        $operatorFee = Config::fetchFloatOrFail(Config::OPERATOR_RX_FEE);

        return $this->computeTotalFee($licenseFee, $operatorFee);
    }

    public function computeTotalFee(float $licenseFee, float $operatorFee): float
    {
        return round($licenseFee + (1 - $licenseFee) * $operatorFee, self::FEE_PRECISION_MAXIMUM);
    }
}
