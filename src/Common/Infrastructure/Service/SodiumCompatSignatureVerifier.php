<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

namespace Adshares\Common\Infrastructure\Service;

use Adshares\Common\Application\Service\Exception\SignatureVerifierException;
use Adshares\Common\Application\Service\SignatureVerifier;
use DateTimeInterface;
use SodiumException;
use Throwable;

class SodiumCompatSignatureVerifier implements SignatureVerifier
{
    public function createFromTransactionId(
        string $privateKey,
        string $transactionId,
        string $accountAddress,
        DateTimeInterface $date
    ): string {
        $message = $this->createMessageHashFromTransactionId($transactionId, $accountAddress, $date);
        try {
            return Sodium::sign($privateKey, $message);
        } catch (SodiumException $exception) {
            throw new SignatureVerifierException(
                sprintf(
                    'Cannot create a signature (txid: %s, address: %s, date: %s).',
                    $transactionId,
                    $accountAddress,
                    $date->format(DateTimeInterface::ATOM)
                )
            );
        }
    }

    public function verifyTransactionId(
        string $publicKey,
        string $signature,
        string $transactionId,
        string $accountAddress,
        DateTimeInterface $date
    ): bool {
        $message = $this->createMessageHashFromTransactionId($transactionId, $accountAddress, $date);
        try {
            return Sodium::verify($signature, $message, $publicKey);
        } catch (SodiumException $exception) {
            throw new SignatureVerifierException(
                sprintf(
                    'Verification failed. Wrong signature (%s) or public key (%s).',
                    $signature,
                    $publicKey
                )
            );
        }
    }

    public function createFromNonce(string $privateKey, string $nonce, DateTimeInterface $date): string
    {
        $message = $this->createMessageHashFromNonce($nonce, $date);
        try {
            return Sodium::sign($privateKey, $message);
        } catch (SodiumException $exception) {
            throw new SignatureVerifierException(
                sprintf(
                    'Cannot create a signature (nonce: %s, date: %s).',
                    $nonce,
                    $date->format(DateTimeInterface::ATOM)
                )
            );
        }
    }

    public function verifyNonce(
        string $publicKey,
        string $signature,
        string $nonce,
        DateTimeInterface $date
    ): bool {
        $message = $this->createMessageHashFromNonce($nonce, $date);
        try {
            return Sodium::verify($signature, $message, $publicKey);
        } catch (Throwable $exception) {
            throw new SignatureVerifierException(
                sprintf(
                    'Verification failed. Wrong signature (%s) or public key (%s).',
                    $signature,
                    $publicKey
                )
            );
        }
    }

    private function createMessageHashFromTransactionId(
        string $transactionId,
        string $accountAddress,
        DateTimeInterface $date
    ): string {
        return sha1($transactionId . $date->format('U') . $accountAddress);
    }

    private function createMessageHashFromNonce(string $nonce, DateTimeInterface $date): string
    {
        return sha1($nonce . $date->format('U'));
    }
}
