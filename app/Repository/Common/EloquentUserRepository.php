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

namespace Adshares\Adserver\Repository\Common;

use Adshares\Adserver\Http\Requests\Filter\BoolFilter;
use Adshares\Adserver\Http\Requests\Filter\Filter;
use Adshares\Adserver\Http\Requests\Filter\FilterCollection;
use Adshares\Adserver\Http\Requests\Order\OrderBy;
use Adshares\Adserver\Http\Requests\Order\OrderByCollection;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\ViewModel\Role;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

class EloquentUserRepository implements UserRepository
{
    private int $aliasCount = 0;

    public function fetchUsers(
        ?FilterCollection $filters = null,
        ?OrderByCollection $orderBy = null,
        ?int $perPage = null
    ): CursorPaginator {
        $builder = User::query();

        if (null !== $filters) {
            foreach ($filters->getFilters() as $filter) {
                $builder = $this->appendFilter($builder, $filter);
            }
        }

        if (null !== $orderBy) {
            foreach ($orderBy->getOrderBy() as $order) {
                $builder = $this->appendOrderBy($builder, $order);
            }
        }

        return $builder->orderBy('id')
            ->tokenPaginate($perPage);
    }

    private function appendFilter(Builder $builder, Filter $filter): Builder
    {
        switch ($filter->getName()) {
            case 'adminConfirmed':
                if ($filter instanceof BoolFilter) {
                    if ($filter->isChecked()) {
                        $builder->whereNotNull('admin_confirmed_at');
                    } else {
                        $builder->whereNull('admin_confirmed_at');
                    }
                }
                break;
            case 'emailConfirmed':
                if ($filter instanceof BoolFilter) {
                    if ($filter->isChecked()) {
                        $builder->whereNotNull('email_confirmed_at');
                    } else {
                        $builder->whereNull('email_confirmed_at');
                    }
                }
                break;
            case 'query':
                foreach ($filter->getValues() as $query) {
                    $builder = $this->appendQuery($builder, $query);
                }
                break;
            case 'role':
                $roleToColumnMap = [
                    Role::Admin->value => 'is_admin',
                    Role::Advertiser->value => 'is_advertiser',
                    Role::Agency->value => 'is_agency',
                    Role::Moderator->value => 'is_moderator',
                    Role::Publisher->value => 'is_publisher',
                ];
                $columns = array_map(fn($role) => $roleToColumnMap[$role], $filter->getValues());
                $builder->where(function (Builder $sub) use ($columns) {
                    foreach ($columns as $column) {
                        $sub->orwhere($column, '=', '1');
                    }
                });
                break;
        }
        return $builder;
    }

    private function appendOrderBy(Builder $builder, OrderBy $orderBy): Builder
    {
        switch ($orderBy->getColumn()) {
            case 'bonusBalance':
                $set = UserLedgerEntry::queryForEntriesRelevantForBonusBalance()
                    ->select(DB::raw('user_id, SUM(amount) as bonus_balance'))
                    ->groupBy('user_id');
                $alias = $this->getUniqueAlias();
                $builder->leftJoinSub($set, $alias, function ($join) use ($alias) {
                    $join->on('users.id', '=', $alias . '.user_id');
                })->orderBy('bonus_balance', $orderBy->getDirection())
                    ->select(['*', DB::raw('IFNULL(bonus_balance, 0) AS bonus_balance')]);
                break;
            case 'campaignCount':
                $set = Campaign::where('status', Campaign::STATUS_ACTIVE)
                    ->where(function ($subBuilder) {
                        $subBuilder->where('time_end', '>', new DateTimeImmutable())->orWhere('time_end', null);
                    })
                    ->select(DB::raw('user_id, COUNT(*) as campaign_count'))
                    ->groupBy('user_id');
                $alias = $this->getUniqueAlias();
                $builder->leftJoinSub($set, $alias, function ($join) use ($alias) {
                    $join->on('users.id', '=', $alias . '.user_id');
                })->orderBy('campaign_count', $orderBy->getDirection())
                    ->select(['*', DB::raw('IFNULL(campaign_count, 0) AS campaign_count')]);
                break;
            case 'connectedWallet':
                $builder->orderBy('wallet_address', $orderBy->getDirection());
                break;
            case 'lastActiveAt':
                $builder->orderBy('last_active_at', $orderBy->getDirection());
                break;
            case 'siteCount':
                $set = Site::where('status', Site::STATUS_ACTIVE)
                    ->select(DB::raw('user_id, COUNT(*) as site_count'))
                    ->groupBy('user_id');
                $alias = $this->getUniqueAlias();
                $builder->leftJoinSub($set, $alias, function ($join) use ($alias) {
                    $join->on('users.id', '=', $alias . '.user_id');
                })->orderBy('site_count', $orderBy->getDirection())
                    ->select(['*', DB::raw('IFNULL(site_count, 0) AS site_count')]);
                break;
            case 'walletBalance':
                $set = UserLedgerEntry::queryForEntriesRelevantForWalletBalance()
                    ->select(DB::raw('user_id, SUM(amount) as wallet_balance'))
                    ->groupBy('user_id');
                $alias = $this->getUniqueAlias();
                $builder->leftJoinSub($set, $alias, function ($join) use ($alias) {
                    $join->on('users.id', '=', $alias . '.user_id');
                })->orderBy('wallet_balance', $orderBy->getDirection())
                    ->select(['*', DB::raw('IFNULL(wallet_balance, 0) AS wallet_balance')]);
                break;
            case 'withdrawableBalance':
                $set = UserLedgerEntry::queryForEntriesRelevantForWithdrawableBalance()
                    ->select(DB::raw('user_id, SUM(amount) as withdrawable_balance'))
                    ->groupBy('user_id');
                $alias = $this->getUniqueAlias();
                $builder->leftJoinSub($set, $alias, function ($join) use ($alias) {
                    $join->on('users.id', '=', $alias . '.user_id');
                })->orderBy('withdrawable_balance', $orderBy->getDirection())
                    ->select(['*', DB::raw('IFNULL(withdrawable_balance, 0) AS withdrawable_balance')]);
                break;
            default:
                $builder->orderBy($orderBy->getColumn(), $orderBy->getDirection());
                break;
        }
        return $builder;
    }

    private function appendQuery(Builder $builder, string $query): Builder
    {
        $siteUserIds = Site::where('domain', 'LIKE', '%' . $query . '%')
            ->whereNull('deleted_at')
            ->select(['user_id']);
        $campaignUserIds = Campaign::where('landing_url', 'LIKE', '%' . $query . '%')
            ->whereNull('deleted_at')
            ->select(['user_id']);
        $set = $campaignUserIds->union($siteUserIds);

        $alias = $this->getUniqueAlias();
        $builder->leftJoinSub($set, $alias, function ($join) use ($alias) {
            $join->on('users.id', '=', $alias . '.user_id');
        });

        $builder->where(function (Builder $sub) use ($query, $alias) {
            $sub->where('email', 'LIKE', '%' . $query . '%')
                ->orWhere('wallet_address', 'LIKE', '%' . $query . '%')
                ->orWhereNotNull($alias . '.user_id');
        });

        return $builder;
    }

    protected function getUniqueAlias(): string
    {
        return sprintf('s%d', $this->aliasCount++);
    }
}
