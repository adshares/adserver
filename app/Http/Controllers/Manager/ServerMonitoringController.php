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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\UserLedgerEntry;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ServerMonitoringController extends Controller
{
    private const ALLOWED_KEYS = [
        'hosts',
        'wallet',
    ];

    public function fetch(string $key): JsonResponse
    {
        if (!in_array($key, self::ALLOWED_KEYS)) {
            throw new UnprocessableEntityHttpException(sprintf('Key `%s` is not supported', $key));
        }

        $signature = Str::camel('handle_' . $key);
        $data = $this->{$signature}();

        return self::json($data);
    }

    private function handleHosts(): array
    {
        $hosts = NetworkHost::all()->map(function ($host) {
            /** @var NetworkHost $host */
            $info = $host->info;
            $statistics = $info->getStatistics()?->toArray() ?? [];
            return [
                'id' => $host->id,
                'status' => $host->status,
                'name' => $info->getName(),
                'url' => $host->host,
                'walletAddress' => $host->address,
                'lastBroadcast' => $host->last_broadcast->format(DateTimeInterface::ATOM),
                'lastSynchronization' => $host->last_synchronization?->format(DateTimeInterface::ATOM),
                'campaignCount' => $statistics['campaigns'] ?? 0,
                'siteCount' => $statistics['sites'] ?? 0,
                'connectionErrorCount' => $host->failed_connection,
                'infoJson' => $info->toArray(),
            ];
        })->all();
        return ['hosts' => $hosts];
    }

    private function handleWallet(): array
    {
        return [
            'wallet' => [
                'balance' => UserLedgerEntry::getBalanceForAllUsers(),
                'unusedBonuses' => UserLedgerEntry::getBonusBalanceForAllUsers(),
            ]
        ];
    }

    public function resetHost(int $hostId): JsonResponse
    {
        $host = NetworkHost::find($hostId);
        if (null === $host) {
            throw new UnprocessableEntityHttpException('Invalid id');
        }

        $host->resetConnectionErrorCounter();

        return self::json();
    }
}
