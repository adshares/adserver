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

namespace Adshares\Adserver\Services;

use Adshares\Adserver\Models\NetworkPayment;

final class PaymentProcessingResult
{
    /** @var string */
    private $licenseAccount;

    /** @var string */
    private $adServerAddress;

    /** @var int */
    private $licenceFee;

    /** @var int */
    private $adsPaymentId;

    /** @var int */
    private $paidAmount;

    public function __construct(
        string $licenseAccount,
        string $adServerAddress,
        int $licenceFee,
        int $adsPaymentId,
        int $paidAmount
    ) {
        $this->licenseAccount = $licenseAccount;
        $this->adServerAddress = $adServerAddress;
        $this->licenceFee = $licenceFee;
        $this->adsPaymentId = $adsPaymentId;
        $this->paidAmount = $paidAmount;
    }

    public static function empty(): self
    {
        return new self(
            '0000-00000000-XXXX',
            '0000-00000000-XXXX',
            0, 0, 0
        );
    }

    public function add(PaymentProcessingResult $result): PaymentProcessingResult
    {
        return new self(
            $this->licenseAccount,
            $this->adServerAddress,
            $this->licenceFee + $result->licenceFee,
            $this->adsPaymentId,
            $this->paidAmount
        );
    }

    public function sendLicenseFee(): void
    {
        NetworkPayment::registerNetworkPayment(
            $this->licenseAccount,
            $this->adServerAddress,
            $this->licenceFee,
            $this->adsPaymentId
        );
    }

    public function paidAmount(): int
    {
        return $this->paidAmount;
    }
}
