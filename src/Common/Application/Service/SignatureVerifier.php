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

namespace Adshares\Common\Application\Service;

use Adshares\Common\Application\Service\Exception\SignatureVerifierException;
use DateTimeInterface;

interface SignatureVerifier
{
    /**
     * @throws SignatureVerifierException
     */
    public function createFromTransactionId(
        string $privateKey,
        string $transactionId,
        string $accountAddress,
        DateTimeInterface $date
    ): string;

    /**
     * @throws SignatureVerifierException
     */
    public function verifyTransactionId(
        string $publicKey,
        string $signature,
        string $transactionId,
        string $accountAddress,
        DateTimeInterface $date
    ): bool;

    /**
     * @throws SignatureVerifierException
     */
    public function createFromNonce(string $privateKey, string $nonce, DateTimeInterface $date): string;

    /**
     * @throws SignatureVerifierException
     */
    public function verifyNonce(
        string $publicKey,
        string $signature,
        string $nonce,
        DateTimeInterface $date
    ): bool;
}
