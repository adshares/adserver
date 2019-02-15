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

namespace App\Verifier;

use RuntimeException;
use function sodium_crypto_sign_detached;
use function sodium_crypto_sign_secretkey;
use function sodium_crypto_sign_seed_keypair;
use SodiumException;

class SodiumCompatSignatureVerifier implements SignatureVerifierInterface
{
    /** @var string */
    private $privateKey;

    public function __construct(string $privateKey)
    {
        $this->privateKey = $privateKey;
    }

    public function create(array $keywords, string $bannerId): string
    {
        $message = $this->createMessageHash($keywords, $bannerId);

        try {
            $key_pair = sodium_crypto_sign_seed_keypair(hex2bin($this->privateKey));
            $key_secret = sodium_crypto_sign_secretkey($key_pair);

            return bin2hex(sodium_crypto_sign_detached($message, $key_secret));
        } catch (SodiumException $exception) {
            throw new RuntimeException('Cannot create a signature');
        }
    }

    private function createMessageHash(array $keywords, string $bannerId): string
    {
        return sha1(implode('_', $keywords) . '.' . $bannerId);
    }
}
