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

namespace Adshares\Demand\Application\Service;

use Adshares\Common\Application\Service\Ads;
use Adshares\Common\Application\Service\SignatureVerifier;
use DateTime;

class PaymentDetailsVerify
{
    private $signatureVerifier;

    private $adsClient;

    public function __construct(SignatureVerifier $signatureVerifier, Ads $adsClient)
    {
        $this->signatureVerifier = $signatureVerifier;
        $this->adsClient = $adsClient;
    }

    public function verify(string $signature, string $transactionId, string $accountAddress, DateTime $date): bool
    {
        $publicKey = $this->adsClient->getPublicKeyByAccountAddress($accountAddress);

        return $this->signatureVerifier->verify($publicKey, $signature, $transactionId, $accountAddress, $date);
    }
}
