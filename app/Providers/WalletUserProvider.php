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

namespace Adshares\Adserver\Providers;

use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Utilities\EthUtils;
use Adshares\Common\Application\Service\Ads;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;

class WalletUserProvider extends EloquentUserProvider
{
    private Ads $adsClient;

    public function __construct(Ads $adsClient, HasherContract $hasher, $model)
    {
        parent::__construct($hasher, $model);
        $this->adsClient = $adsClient;
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (!array_key_exists('wallet_address', $credentials)) {
            return parent::retrieveByCredentials($credentials);
        }

        return $this->createModel()
            ->newQuery()
            ->where('wallet_address', $credentials['wallet_address'])
            ->first();
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if (array_key_exists('password', $credentials)) {
            return parent::validateCredentials($user, $credentials);
        }
        if (!array_key_exists('token', $credentials) || !array_key_exists('signature', $credentials)) {
            return false;
        }
        if (false === ($token = Token::check($credentials['token'], null, Token::WALLET_LOGIN))) {
            return false;
        }

        switch ($user->wallet_address->getNetwork()) {
            case WalletAddress::NETWORK_ADS:
                return $this->adsClient->verifyMessage(
                    $credentials['signature'],
                    $token['payload']['message'],
                    $user->wallet_address->getAddress()
                );
            case WalletAddress::NETWORK_BSC:
                return EthUtils::verifyMessage(
                    $credentials['signature'],
                    $token['payload']['message'],
                    $user->wallet_address->getAddress()
                );
        }
        return false;
    }
}
