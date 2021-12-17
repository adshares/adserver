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

namespace Adshares\Common\Infrastructure\Service;

use SodiumException;

final class Web3
{
    /**
     * @throws SodiumException
     */
    public static function sign(
        string $privateKey,
        string $message
    ): string {
        $key_pair = sodium_crypto_sign_seed_keypair(hex2bin($privateKey));
        $key_secret = sodium_crypto_sign_secretkey($key_pair);
        return bin2hex(sodium_crypto_sign_detached($message, $key_secret));
    }


    public static function verify(
        string $publicKey,
        string $signature,
        string $message
    ): bool {
        return sodium_crypto_sign_verify_detached(hex2bin($signature), $message, hex2bin($publicKey));
    }
}
