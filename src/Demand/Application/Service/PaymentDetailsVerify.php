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

namespace Adshares\Demand\Application\Service;

use Adshares\Common\Application\Service\Ads;
use Adshares\Common\Application\Service\Exception\SignatureVerifierException;
use Adshares\Common\Application\Service\SignatureVerifier;
use DateTimeInterface;

class PaymentDetailsVerify
{
    public function __construct(
        private readonly SignatureVerifier $signatureVerifier,
        private readonly Ads $adsClient,
    ) {
    }

    public function verify(
        string $signature,
        string $transactionId,
        string $accountAddress,
        DateTimeInterface $date,
    ): bool {
        $publicKey = $this->adsClient->getPublicKeyByAccountAddress($accountAddress);
        try {
            return $this->signatureVerifier->verifyTransactionId(
                $publicKey,
                $signature,
                $transactionId,
                $accountAddress,
                $date
            );
        } catch (SignatureVerifierException) {
            return false;
        }
    }
}
