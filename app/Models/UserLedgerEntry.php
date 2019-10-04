<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Facades\DB;
use Adshares\Common\Exception\InvalidArgumentException;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as DatabaseBuilder;
use Illuminate\Support\Facades\Log;
use function array_filter;
use function array_merge;
use function in_array;
use function min;
use function sprintf;
use const PHP_INT_MAX;

/**
 * @mixin Builder
 * @property int status
 * @property int type
 */
class UserLedgerEntry extends Model
{
    use SoftDeletes;

    public const INDEX_USER_ID = 'user_ledger_entry_user_id_index';

    public const INDEX_CREATED_AT = 'user_ledger_entry_created_at_index';

    public const STATUS_ACCEPTED = 0;

    public const STATUS_PENDING = 1;

    public const STATUS_REJECTED = 2;

    public const STATUS_BLOCKED = 3;

    public const STATUS_PROCESSING = 4;

    public const STATUS_AWAITING_APPROVAL = 5;

    public const STATUS_CANCELED = 6;

    public const STATUS_SYS_ERROR = 126;

    public const STATUS_NET_ERROR = 127;

    public const TYPE_UNKNOWN = 0;

    public const TYPE_DEPOSIT = 1;

    public const TYPE_WITHDRAWAL = 2;

    public const TYPE_AD_INCOME = 3;

    public const TYPE_AD_EXPENSE = 4;

    public const TYPE_BONUS_INCOME = 5;

    public const TYPE_BONUS_EXPENSE = 6;

    public const ALLOWED_STATUS_LIST = [
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_PROCESSING,
        self::STATUS_PENDING,
        self::STATUS_BLOCKED,
        self::STATUS_AWAITING_APPROVAL,
        self::STATUS_CANCELED,
        self::STATUS_SYS_ERROR,
        self::STATUS_NET_ERROR,
    ];

    public const ALLOWED_TYPE_LIST = [
        self::TYPE_UNKNOWN,
        self::TYPE_DEPOSIT,
        self::TYPE_WITHDRAWAL,
        self::TYPE_AD_INCOME,
        self::TYPE_AD_EXPENSE,
        self::TYPE_BONUS_INCOME,
        self::TYPE_BONUS_EXPENSE,
    ];

    public const CREDIT_TYPES = [
        self::TYPE_DEPOSIT,
        self::TYPE_AD_INCOME,
        self::TYPE_BONUS_INCOME,
    ];

    public const DEBIT_TYPES = [
        self::TYPE_WITHDRAWAL,
        self::TYPE_AD_EXPENSE,
        self::TYPE_BONUS_EXPENSE,
    ];

    private const AWAITING_PAYMENTS = [
        self::STATUS_PROCESSING,
        self::STATUS_PENDING,
        self::STATUS_BLOCKED,
        self::STATUS_AWAITING_APPROVAL,
        self::STATUS_SYS_ERROR,
        self::STATUS_NET_ERROR,
    ];

    protected $dates = [
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'int',
        'amount' => 'int',
        'status' => 'int',
        'type' => 'int',
        'user_id' => 'int',
    ];

    public static function waitingPayments(): int
    {
        return (int)self::queryModificationForAwaitingPayments(self::query())
            ->sum('amount');
    }

    private static function queryModificationForAwaitingPayments(Builder $query): Builder
    {
        return $query->whereIn('status', self::AWAITING_PAYMENTS)
            ->where('amount', '<', 0);
    }

    private static function queryForEntriesRelevantForBalance()
    {
        return self::where(function (Builder $query) {
            $query->where('status', self::STATUS_ACCEPTED)
                ->orWhere(function (Builder $query) {
                    self::queryModificationForAwaitingPayments($query);
                });
        })->whereIn('type', array_merge(self::CREDIT_TYPES, self::DEBIT_TYPES));
    }

    private static function queryForEntriesRelevantForWalletBalance()
    {
        return self::queryForEntriesRelevantForBalance()
            ->whereNotIn('type', [self::TYPE_BONUS_INCOME, self::TYPE_BONUS_EXPENSE]);
    }

    private static function queryForEntriesRelevantForBonusBalance()
    {
        return self::queryForEntriesRelevantForBalance()
            ->whereIn('type', [self::TYPE_BONUS_INCOME, self::TYPE_BONUS_EXPENSE]);
    }

    public static function getBalanceForAllUsers(): int
    {
        return (int)self::queryForEntriesRelevantForBalance()
            ->sum('amount');
    }

    public static function getUnusedBonusesForAllUsers(): int
    {
        return (int)self::queryForEntriesRelevantForBalance()->where('type', self::TYPE_BONUS_INCOME)->sum('amount')
            - (int)self::queryForEntriesRelevantForBalance()->where('type', self::TYPE_BONUS_EXPENSE)->sum('amount');
    }

    public static function getWalletBalanceForAllUsers(): int
    {
        return (int)self::queryForEntriesRelevantForWalletBalance()
            ->sum('amount');
    }

    public static function getBonusBalanceForAllUsers(): int
    {
        return (int)self::queryForEntriesRelevantForBonusBalance()
            ->sum('amount');
    }

    public static function getBalanceByUserId(int $userId): int
    {
        $amount = (int)self::queryForEntriesRelevantForBalanceByUserId($userId)
            ->sum('amount');

//        if ($amount < 0) {
//            throw new UserLedgerException("Negative Balance ($amount)");
//        }

        return $amount;
    }

    public static function getWalletBalanceByUserId(int $userId): int
    {
        $amount = (int)self::queryForEntriesRelevantForWalletBalanceByUserId($userId)
            ->sum('amount');

//        if ($amount < 0) {
//            throw new UserLedgerException('Negative Balance');
//        }

        return $amount;
    }

    public static function getBonusBalanceByUserId(int $userId): int
    {
        $amount = (int)self::queryForEntriesRelevantForBonusBalanceByUserId($userId)
            ->sum('amount');

//        if ($amount < 0) {
//            throw new UserLedgerException('Negative Balance');
//        }

        return $amount;
    }

    public static function queryForEntriesRelevantForBalanceByUserId(int $userId): Builder
    {
        return self::queryForEntriesRelevantForBalance()
            ->where('user_id', $userId);
    }

    public static function queryForEntriesRelevantForWalletBalanceByUserId(int $userId): Builder
    {
        return self::queryForEntriesRelevantForWalletBalance()
            ->where('user_id', $userId);
    }

    public static function queryForEntriesRelevantForBonusBalanceByUserId(int $userId): Builder
    {
        return self::queryForEntriesRelevantForBonusBalance()
            ->where('user_id', $userId);
    }

    public static function construct(int $userId, int $amount, int $status, int $type): self
    {
        $userLedgerEntry = new self();
        $userLedgerEntry->user_id = $userId;
        $userLedgerEntry->amount = $amount;
        $userLedgerEntry->status = $status;
        $userLedgerEntry->type = $type;

        return $userLedgerEntry;
    }

    public static function constructWithAddressAndTransaction(
        int $userId,
        int $amount,
        int $status,
        int $type,
        string $addressFrom,
        string $addressTo,
        string $transactionId
    ): self {
        return self::construct($userId, $amount, $status, $type)
            ->addressed($addressFrom, $addressTo)
            ->processed($transactionId);
    }

    public static function pushBlockedToProcessing(): void
    {
        self::blockedEntries()
            ->update(['status' => self::STATUS_PROCESSING]);
    }

    public static function removeProcessingExpenses(): void
    {
        self::where('status', self::STATUS_PROCESSING)
            ->whereIn('type', [self::TYPE_AD_EXPENSE, self::TYPE_BONUS_EXPENSE])
            ->delete();
    }

    public static function fetchBlockedAmountByUserId(int $userId): int
    {
        return (int)self::blockedEntriesByUserId($userId)->sum('amount');
    }

    private static function blockedEntries(): Builder
    {
        return self::where('status', self::STATUS_BLOCKED)
            ->whereIn('type', [self::TYPE_AD_EXPENSE, self::TYPE_BONUS_EXPENSE]);
    }

    private static function blockedEntriesByUserId(int $userId): Builder
    {
        return self::blockedEntries()->where('user_id', $userId);
    }

    private static function addAdExpense(int $status, int $userId, int $total, int $maxBonus): array
    {
        if ($total < 0 || $maxBonus < 0) {
            throw new InvalidArgumentException(
                sprintf('Values need to be non-negative - User [%s].', $userId)
            );
        }

        $bonusableAmount = (int)max(min($total, $maxBonus, self::getBonusBalanceByUserId($userId)), 0);
        $payableAmount = (int)max(min($total - $bonusableAmount, self::getWalletBalanceByUserId($userId)), 0);

        if ($total > $bonusableAmount + $payableAmount) {
            throw new InvalidArgumentException(
                sprintf('Insufficient funds for User [%s] when adding ad expense.', $userId)
            );
        }

        return array_filter([
            self::insertAdExpense($status, $userId, $bonusableAmount, self::TYPE_BONUS_EXPENSE),
            self::insertAdExpense($status, $userId, $payableAmount, self::TYPE_AD_EXPENSE),
        ]);
    }

    public static function blockAdExpense(int $userId, int $totalAmount, int $maxBonus = PHP_INT_MAX): array
    {
        $adExpenses = self::addAdExpense(self::STATUS_BLOCKED, $userId, $totalAmount, $maxBonus);

        foreach ($adExpenses as $adExpense) {
            /** @var UserLedgerEntry $adExpense */
            Log::debug(
                sprintf(
                    '[UserLedgerEntry] Blocked %d clicks (%s)',
                    $adExpense->amount,
                    $adExpense->typeAsString()
                )
            );
        }

        return $adExpenses;
    }

    public static function releaseBlockedAdExpense(int $userId): void
    {
        $blockedEntries = self::blockedEntriesByUserId($userId);
        $amount = self::fetchBlockedAmountByUserId($userId);
        $blockedEntries->delete();

        Log::debug(sprintf('[UserLedgerEntry] Release blocked %d clicks', $amount));
    }

    public static function processAdExpense(int $userId, int $totalAmount, int $maxBonus = PHP_INT_MAX): array
    {
        return self::addAdExpense(self::STATUS_ACCEPTED, $userId, $totalAmount, $maxBonus);
    }

    public static function awardBonusToUser(User $user, int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Awarded bonus has to be more than 0');
        }

        self::construct(
            $user->id,
            $amount,
            self::STATUS_ACCEPTED,
            self::TYPE_BONUS_INCOME
        )->save();
    }

    private static function insertAdExpense(int $status, int $userId, int $amount, int $type): ?self
    {
        if ($amount === 0) {
            return null;
        }

        $obj = self::construct(
            $userId,
            -$amount,
            $status,
            $type
        );
        $obj->save();

        return $obj;
    }

    public function setStatusAttribute(int $status): void
    {
        $this->failIfStatusNotAllowed($status);

        $this->attributes['status'] = $status;
    }

    public function addressed(string $addressFrom, string $addressTo): self
    {
        $this->address_from = $addressFrom;
        $this->address_to = $addressTo;

        return $this;
    }

    private function failIfStatusNotAllowed(int $status): void
    {
        if (!in_array($status, self::ALLOWED_STATUS_LIST, true)) {
            throw new InvalidArgumentException("Status $status not allowed");
        }
    }

    public function processed(string $transactionId)
    {
        $this->txid = $transactionId;

        return $this;
    }

    private function typeAsString(): string
    {
        switch ($this->type) {
            case self::TYPE_DEPOSIT:
                $type = 'deposit';
                break;
            case self::TYPE_WITHDRAWAL:
                $type = 'withdrawal';
                break;
            case self::TYPE_AD_INCOME:
                $type = 'ad income';
                break;
            case self::TYPE_AD_EXPENSE:
                $type = 'ad expense';
                break;
            case self::TYPE_BONUS_INCOME:
                $type = 'bonus income';
                break;
            case self::TYPE_BONUS_EXPENSE:
                $type = 'bonus expense';
                break;
            default:
                $type = 'unknown';
                break;
        }

        return $type;
    }

    public static function getBillingHistoryBuilder(
        int $userId,
        array $types,
        ?DateTime $from,
        ?DateTime $to
    ): DatabaseBuilder {
        return DB::table('user_ledger_entries')->select(
            [
                DB::raw('MAX(id) AS id'),
                DB::raw('SUM(amount) AS amount'),
                'type',
                'status',
                'address_from',
                'address_to',
                'txid',
                DB::raw('MAX(created_at) AS created_at'),
            ]
        )->fromSub(
            function (DatabaseBuilder $subQuery) use ($userId, $types, $from, $to) {
                $subQuery->from('user_ledger_entries')->select(
                    [
                        'id',
                        'amount',
                        'type',
                        'status',
                        DB::raw(
                            DB::isSQLite()
                                ? 'CASE WHEN type IN (3, 4, 5, 6) AND status = 0'
                                .' THEN null ELSE address_from END AS address_from'
                                : 'IF(type IN (3, 4, 5, 6) AND status = 0, null, address_from) AS address_from'
                        ),
                        'address_to',
                        DB::raw(
                            DB::isSQLite()
                                ? 'CASE WHEN type IN (3, 4, 5, 6) AND status = 0 THEN null ELSE txid END AS txid'
                                : 'IF(type IN (3, 4, 5, 6) AND status = 0, null, txid) AS txid'
                        ),
                        'created_at',
                        DB::raw(
                            DB::isSQLite()
                                ? 'CASE WHEN type IN (3, 4, 5, 6) AND status = 0 '
                                .'THEN date(created_at) ELSE created_at END AS date_helper'
                                : 'IF(type IN (3, 4, 5, 6) AND status = 0, date(created_at), created_at) AS date_helper'
                        ),
                    ]
                )->where('user_id', $userId)->whereNull('deleted_at');

                if (!empty($types)) {
                    $subQuery->whereIn('type', $types);
                }

                if (null !== $from) {
                    $subQuery->where('created_at', '>=', $from);
                }

                if (null !== $to) {
                    $subQuery->where('created_at', '<=', $to);
                }

                return $subQuery;
            },
            'u'
        )->groupBy(['date_helper', 'type', 'status', 'txid', 'address_from', 'address_to']);
    }
}
