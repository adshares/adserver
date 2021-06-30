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

namespace Adshares\Adserver\Services;

class PaymentDetailsFeeCalculator
{
    /** @var float $licenseFeeCoefficient */
    private $licenseFeeCoefficient;

    /** @var float $operatorFeeCoefficient */
    private $operatorFeeCoefficient;

    public function __construct(
        float $licenseFeeCoefficient,
        float $operatorFeeCoefficient
    ) {
        $this->licenseFeeCoefficient = $licenseFeeCoefficient;
        $this->operatorFeeCoefficient = $operatorFeeCoefficient;
    }

    public function calculateFee(int $amountBeforeFees): array
    {
        $licenseFee = (int)floor($this->licenseFeeCoefficient * $amountBeforeFees);
        $transferAmountBeforeOperatorFee = $amountBeforeFees - $licenseFee;

        $operatorFee = (int)floor($this->operatorFeeCoefficient * $transferAmountBeforeOperatorFee);
        $amountAfterFees = $transferAmountBeforeOperatorFee - $operatorFee;

        return [
            'event_value' => $amountBeforeFees,
            'license_fee' => $licenseFee,
            'operator_fee' => $operatorFee,
            'paid_amount' => $amountAfterFees,
        ];
    }
}
