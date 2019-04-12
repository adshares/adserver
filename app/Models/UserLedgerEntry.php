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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use function array_merge;
use function in_array;
use function sprintf;

/**
 * @mixin Builder
 * @property int status
 */
class UserLedgerEntry extends Model
{
    use SoftDeletes;

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
        return (int)self::queryForEntriesRelevantForBalanceByUserId($userId)
            ->sum('amount');
    }

    public static function getWalletBalanceByUserId(int $userId): int
    {
        return (int)self::queryForEntriesRelevantForWalletBalanceByUserId($userId)
            ->sum('amount');
    }

    public static function getBonusBalanceByUserId(int $userId): int
    {
        return (int)self::queryForEntriesRelevantForBonusBalanceByUserId($userId)
            ->sum('amount');
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

    private static function addAdExpense(int $status, int $userId, int $amount): array
    {
        if ($amount < 0) {
            throw new InvalidArgumentException(
                sprintf('Amount needs to be non-negative - User [%s].', $userId)
            );
        }

        if (self::getBalanceByUserId($userId) < $amount) {
            throw new InvalidArgumentException(
                sprintf('Insufficient funds for User [%s] when adding ad expense.', $userId)
            );
        }

        $entries = [];
        $bonus = self::getBonusBalanceByUserId($userId);
        if ($bonus > 0) {
            $obj = self::construct(
                $userId,
                -min($bonus, $amount),
                $status,
                self::TYPE_BONUS_EXPENSE
            );
            $obj->save();
            $entries[] = $obj;
        }
        if ($amount > $bonus) {
            $obj = self::construct(
                $userId,
                -($amount - $bonus),
                $status,
                self::TYPE_AD_EXPENSE
            );
            $obj->save();
            $entries[] = $obj;
        }

        return $entries;
    }

    public static function blockAdExpense(int $userId, int $nonNegativeAmount): array
    {
        $adExpenses = self::addAdExpense(self::STATUS_BLOCKED, $userId, $nonNegativeAmount);
        foreach ($adExpenses as $adExpense) {
            /** @var UserLedgerEntry $adExpense */
            Log::info(
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

        Log::info(sprintf('[UserLedgerEntry] Release blocked %d clicks', $amount));
    }

    public static function processAdExpense(int $userId, int $nonNegativeAmount): array
    {
        return self::addAdExpense(self::STATUS_ACCEPTED, $userId, $nonNegativeAmount);
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
}
