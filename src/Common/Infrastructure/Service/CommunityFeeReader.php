<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

use Adshares\Common\Application\Dto\CommunityFee;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Exception\InvalidArgumentException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class CommunityFeeReader
{
    private const CE_LICENSE_ACCOUNT = '0001-00000024-FF89';
    private const CE_LICENSE_FEE = 0.01;
    private const URI = 'https://network.adshares.net/';

    private ?CommunityFee $communityFee = null;

    public function __construct(private readonly Client $client)
    {
    }

    public function getAddress(): AccountId
    {
        return $this->getCommunityFee()?->getAccount() ?? new AccountId(self::CE_LICENSE_ACCOUNT);
    }

    public function getFee(): float
    {
        return $this->getCommunityFee()?->getFee() ?? self::CE_LICENSE_FEE;
    }

    private function getCommunityFee(): ?CommunityFee
    {
        if (null === $this->communityFee) {
            $this->init();
        }
        return $this->communityFee;
    }

    private function init(): void
    {
        try {
            $response = $this->client->get(
                self::URI,
                [
                    'headers' => ['Content-Type' => 'application/json'],
                    'timeout' => 5.0,
                ]
            );
        } catch (GuzzleException $exception) {
            Log::error(sprintf('Cannot init community fee: %s', $exception->getMessage()));
            return;
        }

        $data = json_decode((string)$response->getBody(), true);
        if (!isset($data['community']) || !is_array($data['community'])) {
            Log::error('Cannot init community fee: Invalid `community`');
            return;
        }

        try {
            $this->communityFee = CommunityFee::fromArray($data['community']);
        } catch (InvalidArgumentException $exception) {
            Log::error(sprintf('Cannot init community fee: %s', $exception->getMessage()));
        }
    }
}
