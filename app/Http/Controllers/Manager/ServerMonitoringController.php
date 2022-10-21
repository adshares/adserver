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
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\ServerEventLog;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\ViewModel\ServerEventType;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ServerMonitoringController extends Controller
{
    private const ALLOWED_KEYS = [
        'events',
        'hosts',
        'latest-events',
        'users',
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
        self::validateLimit($limit);
        self::validateTypes($types);
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
            ->tokenPaginate($limit)
            ->withQueryString()
            ->toArray();
    }

    private function handleHosts(Request $request): array
    {
        $limit = $request->query('limit', 10);
        $this->validateLimit($limit);

        $paginator = NetworkHost::orderBy('id')
            ->tokenPaginate($limit)
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
                'error' => $host->error,
            ];
        });
        $paginator->setCollection($collection);

        return $paginator->toArray();
    }

    public function handleLatestEvents(Request $request): array
    {
        $limit = $request->query('limit', 10);
        $types = $request->query('types', []);
        $this->validateLimit($limit);
        self::validateTypes($types);

        return ServerEventLog::getBuilderForFetchingLatest($types)
            ->tokenPaginate($limit)
            ->withQueryString()
            ->toArray();
    }

    private function handleUsers(Request $request): array
    {
        $limit = $request->query('limit', 10);
        $orderBy = $request->query('orderBy');
        $direction = $request->query('direction', 'asc');
        $this->validateLimit($limit);
        $this->validateUserOrderBy($orderBy);
        $this->validateDirection($direction);

        $builder = User::query();

        if ($orderBy) {
            if ('bonusBalance' === $orderBy) {
                $set = UserLedgerEntry::queryForEntriesRelevantForBonusBalance()
                    ->select(DB::raw('user_id, SUM(amount) as bonus_balance'))
                    ->groupBy('user_id');
                $builder->leftJoinSub($set, 's', function ($join) {
                    $join->on('users.id', '=', 's.user_id');
                })->orderBy('bonus_balance', $direction)
                    ->select(['*', DB::raw('IFNULL(bonus_balance, 0) AS bonus_balance')]);
            } elseif ('campaignCount' === $orderBy) {
                $set = Campaign::where('status', Campaign::STATUS_ACTIVE)
                    ->where(function ($subBuilder) {
                        $subBuilder->where('time_end', '>', new DateTimeImmutable())->orWhere('time_end', null);
                    })
                    ->select(DB::raw('user_id, COUNT(*) as campaign_count'))
                    ->groupBy('user_id');
                $builder->leftJoinSub($set, 's', function ($join) {
                    $join->on('users.id', '=', 's.user_id');
                })->orderBy('campaign_count', $direction)
                    ->select(['*', DB::raw('IFNULL(campaign_count, 0) AS campaign_count')]);
            } elseif ('connectedWallet' === $orderBy) {
                $builder->orderBy('wallet_address', $direction);
            } elseif ('lastLogin' === $orderBy) {
                $builder->orderBy('updated_at', $direction);
            } elseif ('siteCount' === $orderBy) {
                $set = Site::where('status', Site::STATUS_ACTIVE)
                    ->select(DB::raw('user_id, COUNT(*) as site_count'))
                    ->groupBy('user_id');
                $builder->leftJoinSub($set, 's', function ($join) {
                    $join->on('users.id', '=', 's.user_id');
                })->orderBy('site_count', $direction)
                    ->select(['*', DB::raw('IFNULL(site_count, 0) AS site_count')]);
            } elseif ('walletBalance' === $orderBy) {
                $set = UserLedgerEntry::queryForEntriesRelevantForWalletBalance()
                    ->select(DB::raw('user_id, SUM(amount) as wallet_balance'))
                    ->groupBy('user_id');
                $builder->leftJoinSub($set, 's', function ($join) {
                    $join->on('users.id', '=', 's.user_id');
                })->orderBy('wallet_balance', $direction)
                    ->select(['*', DB::raw('IFNULL(wallet_balance, 0) AS wallet_balance')]);
            } else {
                $builder->orderBy($orderBy, $direction);
            }
        }

        $paginator = $builder->orderBy('id')
            ->tokenPaginate($limit)
            ->withQueryString();
        $collection = $paginator->getCollection()->map(fn($user) => $this->mapUser($user));
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

    public function banUser(AdminController $adminController, Request $request, int $userId): JsonResponse
    {
        $adminController->banUser($userId, $request);
        return self::json($this->mapUser(User::fetchById($userId)));
    }

    public function confirmUser(AuthController $authController, int $userId): JsonResponse
    {
        $authController->confirm($userId);
        return self::json($this->mapUser(User::fetchById($userId)));
    }

    public function deleteUser(
        AdminController $adminController,
        CampaignRepository $campaignRepository,
        int $userId,
    ): JsonResponse {
        return $adminController->deleteUser($userId, $campaignRepository);
    }

    public function denyAdvertising(AdminController $adminController, int $userId): JsonResponse
    {
        $adminController->denyAdvertising($userId);
        return self::json($this->mapUser(User::fetchById($userId)));
    }

    public function denyPublishing(AdminController $adminController, int $userId): JsonResponse
    {
        $adminController->denyPublishing($userId);
        return self::json($this->mapUser(User::fetchById($userId)));
    }

    public function grantAdvertising(AdminController $adminController, int $userId): JsonResponse
    {
        $adminController->grantAdvertising($userId);
        return self::json($this->mapUser(User::fetchById($userId)));
    }

    public function grantPublishing(AdminController $adminController, int $userId): JsonResponse
    {
        $adminController->grantPublishing($userId);
        return self::json($this->mapUser(User::fetchById($userId)));
    }

    public function switchUserToAgency(AdminController $adminController, int $userId): JsonResponse
    {
        $adminController->switchUserToAgency($userId);
        return self::json($this->mapUser(User::fetchById($userId)));
    }

    public function switchUserToModerator(AdminController $adminController, int $userId): JsonResponse
    {
        $adminController->switchUserToModerator($userId);
        return self::json($this->mapUser(User::fetchById($userId)));
    }

    public function switchUserToRegular(AdminController $adminController, int $userId): JsonResponse
    {
        $adminController->switchUserToRegular($userId);
        return self::json($this->mapUser(User::fetchById($userId)));
    }

    public function unbanUser(AdminController $adminController, int $userId): JsonResponse
    {
        $adminController->unbanUser($userId);
        return self::json($this->mapUser(User::fetchById($userId)));
    }

    private function mapUser(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'adsharesWallet' => [
                'walletBalance' => null !== $user->wallet_balance
                    ? (int)$user->wallet_balance : $user->getWalletBalance(),
                'bonusBalance' => null !== $user->bonus_balance
                    ? (int)$user->bonus_balance : $user->getBonusBalance(),
            ],
            'connectedWallet' => [
                'address' => $user->wallet_address?->getAddress(),
                'network' => $user->wallet_address?->getNetwork(),
            ],
            'roles' => $user->roles,
            'campaignCount' => null !== $user->campaign_count
                ? (int)$user->campaign_count : $user->campaigns()->count(),
            'siteCount' => null !== $user->site_count
                ? (int)$user->site_count : $user->sites()->count(),
            'lastLogin' => $user->updated_at->format(DateTimeInterface::ATOM),
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

    private function validateDirection(array|string|null $direction): void
    {
        $allowedDirections = ['asc', 'desc'];
        if (!in_array($direction, $allowedDirections)) {
            throw new UnprocessableEntityHttpException(
                sprintf('Limit must be any of %s', join(', ', $allowedDirections))
            );
        }
    }

    private static function validateLimit(array|string|null $limit): void
    {
        if (false === filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw new UnprocessableEntityHttpException('Limit must be a positive integer');
        }
    }

    private static function validateTypes(array|string|null $types): void
    {
        if (!is_array($types)) {
            throw new UnprocessableEntityHttpException('Types must be an array');
        }
        foreach ($types as $type) {
            if (!is_string($type) || null === ServerEventType::tryFrom($type)) {
                throw new UnprocessableEntityHttpException(sprintf('Invalid type `%s`', $type));
            }
        }
    }

    private function validateUserOrderBy(array|string|null $orderBy): void
    {
        if (null === $orderBy) {
            return;
        }
        if (!is_string($orderBy)) {
            throw new UnprocessableEntityHttpException('Sorting only by single category is supported');
        }
        if (
            !in_array(
                $orderBy,
                [
                    'bonusBalance',
                    'campaignCount',
                    'connectedWallet',
                    'email',
                    'lastLogin',
                    'siteCount',
                    'walletBalance',
                ]
            )
        ) {
            throw new UnprocessableEntityHttpException(sprintf('Sorting by `%s` is not supported', $orderBy));
        }
    }
}
