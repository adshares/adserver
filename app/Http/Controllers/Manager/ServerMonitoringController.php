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
use Adshares\Adserver\Http\Requests\Filter\FilterCollection;
use Adshares\Adserver\Http\Requests\Filter\FilterType;
use Adshares\Adserver\Http\Requests\Order\OrderByCollection;
use Adshares\Adserver\Http\Resources\HostCollection;
use Adshares\Adserver\Http\Resources\UserCollection;
use Adshares\Adserver\Http\Resources\UserResource;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Repository\Common\ServerEventLogRepository;
use Adshares\Adserver\Repository\Common\UserRepository;
use Adshares\Adserver\ViewModel\Role;
use Adshares\Adserver\ViewModel\ServerEventType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ServerMonitoringController extends Controller
{
    private const ALLOWED_KEYS = [
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

    public function fetchEvents(Request $request, ServerEventLogRepository $repository): array
    {
        $limit = $request->query('limit', 10);
        $filters = FilterCollection::fromRequest($request, [
            'createdAt' => FilterType::Date,
            'type' => FilterType::String,
        ]);
        self::validateLimit($limit);
        self::validateEventFilters($filters);

        return $repository->fetchServerEvents($filters, $limit)
            ->toArray();
    }

    private function handleHosts(Request $request): array
    {
        $limit = $request->query('limit', 10);
        self::validateLimit($limit);

        $paginator = NetworkHost::orderBy('id')
            ->tokenPaginate($limit)
            ->withQueryString();

        return (new HostCollection($paginator))->toArray($request);
    }

    public function fetchLatestEvents(Request $request, ServerEventLogRepository $repository): array
    {
        $limit = $request->query('limit', 10);
        $filters = FilterCollection::fromRequest($request, [
            'type' => FilterType::String,
        ]);
        self::validateLimit($limit);
        self::validateEventFilters($filters);

        return $repository->fetchLatestServerEvents($filters, $limit)
            ->toArray();
    }

    public function fetchUsers(Request $request, UserRepository $userRepository): JsonResource
    {
        $limit = $request->query('limit', 10);
        $filters = FilterCollection::fromRequest($request, [
            'adminConfirmed' => FilterType::Bool,
            'emailConfirmed' => FilterType::Bool,
            'role' => FilterType::String,
        ]);
        $orderBy = OrderByCollection::fromRequest($request);
        $query = self::queryFromRequest($request);
        self::validateLimit($limit);
        self::validateUserFilters($filters);
        self::validateUserOrderBy($orderBy);

        return new UserCollection($userRepository->fetchUsers($filters, $query, $orderBy, $limit));
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

    public function banUser(AdminController $adminController, Request $request, int $userId): JsonResource
    {
        $adminController->banUser($userId, $request);
        return new UserResource(User::fetchById($userId));
    }

    public function confirmUser(AuthController $authController, int $userId): JsonResource
    {
        $authController->confirm($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function deleteUser(
        AdminController $adminController,
        CampaignRepository $campaignRepository,
        int $userId,
    ): JsonResponse {
        return $adminController->deleteUser($userId, $campaignRepository);
    }

    public function denyAdvertising(AdminController $adminController, int $userId): JsonResource
    {
        $adminController->denyAdvertising($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function denyPublishing(AdminController $adminController, int $userId): JsonResource
    {
        $adminController->denyPublishing($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function grantAdvertising(AdminController $adminController, int $userId): JsonResource
    {
        $adminController->grantAdvertising($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function grantPublishing(AdminController $adminController, int $userId): JsonResource
    {
        $adminController->grantPublishing($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function switchUserToAgency(AdminController $adminController, int $userId): JsonResource
    {
        $adminController->switchUserToAgency($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function switchUserToModerator(AdminController $adminController, int $userId): JsonResource
    {
        $adminController->switchUserToModerator($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function switchUserToRegular(AdminController $adminController, int $userId): JsonResource
    {
        $adminController->switchUserToRegular($userId);
        return new UserResource(User::fetchById($userId));
    }

    public function unbanUser(AdminController $adminController, int $userId): JsonResource
    {
        $adminController->unbanUser($userId);
        return new UserResource(User::fetchById($userId));
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

    private static function validateLimit(array|string|null $limit): void
    {
        if (false === filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw new UnprocessableEntityHttpException('Limit must be a positive integer');
        }
    }

    private static function validateEventFilters(?FilterCollection $filters): void
    {
        if (null === $filters) {
            return;
        }
        if (null !== ($filter = $filters->getFilterByName('type'))) {
            foreach ($filter->getValues() as $type) {
                if (null === ServerEventType::tryFrom($type)) {
                    throw new UnprocessableEntityHttpException(
                        sprintf('Filtering by type `%s` is not supported', $type)
                    );
                }
            }
        }
    }

    private static function validateUserOrderBy(?OrderByCollection $orderBy): void
    {
        if (null === $orderBy) {
            return;
        }

        $columns = array_map(fn($orderBy) => $orderBy->getColumn(), $orderBy->getOrderBy());
        foreach ($columns as $column) {
            if (
                !in_array(
                    $column,
                    [
                        'bonusBalance',
                        'campaignCount',
                        'connectedWallet',
                        'email',
                        'lastActiveAt',
                        'siteCount',
                        'walletBalance',
                    ]
                )
            ) {
                throw new UnprocessableEntityHttpException(sprintf('Sorting by `%s` is not supported', $column));
            }
        }
    }

    private static function validateUserFilters(?FilterCollection $filters): void
    {
        if (null === $filters) {
            return;
        }
        if (null !== ($filter = $filters->getFilterByName('role'))) {
            $availableRoles = array_map(fn($role) => $role->value, Role::cases());
            foreach ($filter->getValues() as $role) {
                if (!in_array($role, $availableRoles)) {
                    throw new UnprocessableEntityHttpException(
                        sprintf('Filtering by role `%s` is not supported', $role)
                    );
                }
            }
        }
    }

    private static function queryFromRequest(Request $request): ?string
    {
        $query = $request->query('query');
        if (null === $query || is_string($query)) {
            return $query;
        }
        throw new UnprocessableEntityHttpException('Query must be a string');
    }
}
