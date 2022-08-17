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

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\Serialize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * @mixin Builder
 * @property string uuid
 * @property array payload
 */
class Token extends Model
{
    use AutomateMutators;
    use BinHex;
    use Serialize;

    private const VALIDITY_PERIODS = [
        self::EMAIL_ACTIVATE => 24 * 3600,
        self::EMAIL_CHANGE_STEP_1 => 24 * 3600,
        self::EMAIL_CHANGE_STEP_2 => 24 * 3600,
        self::PASSWORD_CHANGE => 3600,
        self::PASSWORD_RECOVERY => 24 * 3600,
        self::IMPERSONATION => 24 * 3600,
        self::EMAIL_APPROVE_WITHDRAWAL => 3600,
        self::WALLET_CONNECT => 60,
        self::WALLET_CONNECT_CONFIRM => 1 * 3600,
        self::WALLET_LOGIN => 60,
    ];

    private const MINIMAL_AGE_LIMITS = [
        self::EMAIL_ACTIVATE => 5 * 60,
        self::EMAIL_CHANGE_STEP_1 => 5 * 60,
        self::EMAIL_CHANGE_STEP_2 => 5 * 60,
        self::PASSWORD_CHANGE => 2 * 60,
        self::PASSWORD_RECOVERY => 2 * 60,
    ];

    public const EMAIL_ACTIVATE = 'email-activate';

    public const EMAIL_CHANGE_STEP_1 = 'email-change-step1';

    public const EMAIL_CHANGE_STEP_2 = 'email-change-step2';

    public const PASSWORD_CHANGE = 'password-change';

    public const PASSWORD_RECOVERY = 'password-recovery';

    public const IMPERSONATION = 'impersonation';

    public const EMAIL_APPROVE_WITHDRAWAL = 'email-approve-withdrawal';

    public const WALLET_CONNECT = 'wallet-connect';

    public const WALLET_CONNECT_CONFIRM = 'wallet-connect-confirm';

    public const WALLET_LOGIN = 'wallet-login';

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    protected $fillable = [
        'multi_usage',
        'payload',
        'tag',
        'user_id',
        'valid_until',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
        'payload' => 'Serialize',
    ];

    public static function canGenerateToken(User $user, string $tag): bool
    {
        return self::canGenerate($user->id, $tag, self::MINIMAL_AGE_LIMITS[$tag]);
    }

    private static function canGenerate(int $user_id, string $tag, int $older_then_seconds): bool
    {
        if (
            self::where('user_id', $user_id)->where('tag', $tag)->where(
                'created_at',
                '>',
                date('Y-m-d H:i:s', time() - $older_then_seconds)
            )->count()
        ) {
            return false;
        }

        return true;
    }

    public static function check($uuid, int $user_id = null, $tag = null)
    {
        try {
            $uuid = hex2bin($uuid);
        } catch (Throwable $exception) {
            return false;
        }
        $q = self::where('uuid', $uuid)->where('valid_until', '>', date('Y-m-d H:i:s'));

        if (!empty($user_id)) {
            $q->where('user_id', $user_id);
        }

        if (!empty($tag)) {
            $q->where('tag', $tag);
        }

        $token = $q->first();

        if (empty($token)) {
            return false;
        }

        if (!$token->multi_usage) {
            $token->delete();
        }

        return $token->toArray();
    }

    public static function extend($tag, string $tokenId): bool
    {
        $q = self::where('uuid', hex2bin($tokenId))->where('valid_until', '>', date('Y-m-d H:i:s'));

        if (!empty($tag)) {
            $q->where('tag', $tag);
        }

        $token = $q->first();

        if (empty($token)) {
            return false;
        }

        $token->valid_until = date('Y-m-d H:i:s', time() + self::VALIDITY_PERIODS[$tag]);

        $token->save();

        return true;
    }

    public static function generate(string $tag, ?User $user, array $payload = null): self
    {
        return self::generateToken($tag, self::VALIDITY_PERIODS[$tag], optional($user)->id, $payload);
    }

    private static function generateToken(
        string $tag,
        int $valid_until_seconds,
        ?int $user_id = null,
        $payload = null,
        bool $multi_usage = false
    ): self {
        $valid_until = date('Y-m-d H:i:s', time() + $valid_until_seconds);
        return self::create(compact('user_id', 'tag', 'payload', 'valid_until', 'multi_usage'));
    }

    public static function impersonate(User $who, User $asWhom): self
    {
        return self::generateToken(
            self::IMPERSONATION,
            self::VALIDITY_PERIODS[self::IMPERSONATION],
            $who->id,
            $asWhom->id,
            true
        );
    }

    public static function activation(User $user): self
    {
        return self::generateToken(
            self::EMAIL_ACTIVATE,
            self::VALIDITY_PERIODS[self::EMAIL_ACTIVATE],
            $user->id
        );
    }

    public static function fetchExpiredWithdrawals(): Collection
    {
        return self::where('tag', self::EMAIL_APPROVE_WITHDRAWAL)
            ->where('valid_until', '<', date('Y-m-d H:i:s'))
            ->get();
    }

    public static function deleteByUserId(int $userId): void
    {
        self::where('user_id', $userId)->delete();
    }
}
