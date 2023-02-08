<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

use Adshares\Common\Infrastructure\Service\LicenseReader;

class TotalFeeReader
{
    private const FEE_PRECISION_MAXIMUM = 4;

    public function __construct(private readonly LicenseReader $licenseReader)
    {
    }

    public function getTotalFeeDemand(): float
    {
        $licenseFee = $this->licenseReader->getFee(LicenseReader::LICENSE_TX_FEE);
        $operatorFee = config('app.payment_tx_fee');

        return $this->computeTotalFee($licenseFee, $operatorFee);
    }

    public function getTotalFeeSupply(): float
    {
        $licenseFee = $this->licenseReader->getFee(LicenseReader::LICENSE_RX_FEE);
        $operatorFee = config('app.payment_rx_fee');

        return $this->computeTotalFee($licenseFee, $operatorFee);
    }

    public function computeTotalFee(float $licenseFee, float $operatorFee): float
    {
        return round($licenseFee + (1 - $licenseFee) * $operatorFee, self::FEE_PRECISION_MAXIMUM);
    }
}
