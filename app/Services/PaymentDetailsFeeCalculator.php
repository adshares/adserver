<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Services;

class PaymentDetailsFeeCalculator
{
    /** @var int $totalAmount */
    private $totalAmount;

    /** @var int $totalWeight */
    private $totalWeight;

    /** @var float $licenceFee */
    private $licenceFee;

    /** @var float $operatorFee */
    private $operatorFee;

    public function __construct(int $totalAmount, int $totalWeight, float $licenceFee, float $operatorFee)
    {
        $this->totalAmount = $totalAmount;
        $this->totalWeight = $totalWeight;
        $this->licenceFee = $licenceFee;
        $this->operatorFee = $operatorFee;
    }

    public function calculateFee(int $weight): array
    {
        $normalizationFactor = (float)$weight / $this->totalWeight;
        $amountBeforeFees = (int)floor($this->totalAmount * $normalizationFactor);

        $licenceFeeAmount = (int)floor($this->licenceFee * $amountBeforeFees);
        $transferAmountBeforeOperatorFee = $amountBeforeFees - $licenceFeeAmount;

        $operatorFeeAmount = (int)floor($this->operatorFee * $transferAmountBeforeOperatorFee);
        $amountAfterFees = $transferAmountBeforeOperatorFee - $operatorFeeAmount;

        return [
            'event_value' => $amountBeforeFees,
            'licence_fee_amount' => $licenceFeeAmount,
            'operator_fee_amount' => $operatorFeeAmount,
            'paid_amount' => $amountAfterFees,
        ];
    }
}
