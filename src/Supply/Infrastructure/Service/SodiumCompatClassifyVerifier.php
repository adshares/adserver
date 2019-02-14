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

namespace Adshares\Supply\Infrastructure\Service;

use Adshares\Supply\Application\Service\ClassifyVerifier;
use Adshares\Supply\Domain\ValueObject\Classification;
use SodiumException;
use function sodium_crypto_sign_verify_detached;

class SodiumCompatClassifyVerifier implements ClassifyVerifier
{
    /** @var string */
    private $publicKey;

    public function __construct(string $publicKey)
    {

        $this->publicKey = $publicKey;
    }

    public function isVerified(Classification $classification, string $bannerId): bool
    {
        $message = $this->createMessageHash($classification->getKeywords(), $bannerId);
        $signature = hex2bin($classification->getSignature());

        try {
            return sodium_crypto_sign_verify_detached($signature, $message, hex2bin($this->publicKey));
        } catch (SodiumException $exception) {
            return false;
        }
    }

    private function createMessageHash(array $keywords, string $bannerId): string
    {
        return sha1(implode('_', $keywords) . '.' . $bannerId);
    }
}
