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
use Adshares\Adserver\Models\ServerEventLog;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\ViewModel\ServerEventType;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ServerMonitoringController extends Controller
{
    private const ALLOWED_KEYS = [
        'events',
        'hosts',
        'wallet',
    ];

    public function fetch(Request $request, string $key): JsonResponse
    {
        if (!in_array($key, self::ALLOWED_KEYS)) {
            throw new UnprocessableEntityHttpException(sprintf('Key `%s` is not supported', $key));
        }

        $signature = Str::camel('handle_' . $key);
        $data = $this->{$signature}($request);

        return self::json($data);
    }

    public function handleEvents(Request $request): array
    {
        $limit = $request->query('limit', 10);
        $types = $request->query('types', []);
        $from = $request->query('from');
        $to = $request->query('to');
        if (false === filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw new UnprocessableEntityHttpException('Limit must be a positive integer');
        }
        if (!is_array($types)) {
            throw new UnprocessableEntityHttpException('Types must be an array');
        }
        foreach ($types as $type) {
            if (!is_string($type) || null === ServerEventType::tryFrom($type)) {
                throw new UnprocessableEntityHttpException(sprintf('Invalid type `%s`', $type));
            }
        }
        if (null !== $from) {
            if (!is_string($from)) {
                throw new UnprocessableEntityHttpException('`from` must be a string in ISO 8601 format');
            }
            $from = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $from);
            if (false === $from) {
                throw new UnprocessableEntityHttpException('`from` must be in ISO 8601 format');
            }
        }
        if (null !== $to) {
            if (!is_string($to)) {
                throw new UnprocessableEntityHttpException('`to` must be a string in ISO 8601 format');
            }
            $to = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $to);
            if (false === $to) {
                throw new UnprocessableEntityHttpException('`to` must be in ISO 8601 format');
            }
        }
        if (null !== $from && null !== $to && $from > $to) {
            throw new UnprocessableEntityHttpException('Invalid time range: `from` must be earlier than `to`');
        }

        return ServerEventLog::getBuilderForFetching($types, $from, $to)
            ->cursorPaginate($limit)
            ->withQueryString()
            ->toArray();
    }

    private function handleHosts(Request $request): array
    {
        $limit = $request->query('limit', 10);
        if (false === filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw new UnprocessableEntityHttpException('Limit must be a positive integer');
        }

        $paginator = NetworkHost::orderBy('id')
            ->cursorPaginate($limit)
            ->withQueryString();
        $collection = $paginator->getCollection()->map(function ($host) {
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
        });
        $paginator->setCollection($collection);
        return $paginator->toArray();
    }

    private function handleWallet(Request $request): array
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
