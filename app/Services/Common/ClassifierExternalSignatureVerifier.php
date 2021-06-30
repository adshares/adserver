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

namespace Adshares\Adserver\Services\Common;

use Adshares\Adserver\Repository\Common\ClassifierExternalRepository;
use Adshares\Adserver\Utilities\ClassifierExternalKeywordsSerializer;
use Illuminate\Support\Facades\Log;
use SodiumException;

class ClassifierExternalSignatureVerifier
{
    /** @var array */
    private $cachedKeys;

    /** @var ClassifierExternalRepository */
    private $classifierRepository;

    public function __construct(ClassifierExternalRepository $classifierRepository)
    {
        $this->cachedKeys = [];
        $this->classifierRepository = $classifierRepository;
    }

    public function isSignatureValid(
        string $classifierName,
        string $signature,
        string $checksum,
        int $timestamp,
        array $keywords
    ): bool {
        if (null === ($publicKey = $this->getPublicKey($classifierName))) {
            Log::info(sprintf('Unknown classifier (%s)', $classifierName));

            return false;
        }

        $message = $this->createMessage($checksum, $timestamp, $keywords);

        try {
            return sodium_crypto_sign_verify_detached(hex2bin($signature), $message, hex2bin($publicKey));
        } catch (SodiumException $exception) {
            return false;
        }
    }

    private function createMessage(string $checksum, int $timestamp, array $keywords): string
    {
        return hash(
            'sha256',
            hex2bin($checksum) . $timestamp . ClassifierExternalKeywordsSerializer::serialize($keywords)
        );
    }

    private function getPublicKey(string $classifierName): ?string
    {
        if (isset($this->cachedKeys[$classifierName])) {
            return $this->cachedKeys[$classifierName];
        }

        if (null !== ($key = $this->classifierRepository->fetchPublicKeyByClassifierName($classifierName))) {
            $this->cachedKeys[$classifierName] = $key;
        }

        return $key;
    }
}
