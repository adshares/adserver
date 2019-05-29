<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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
use Adshares\Adserver\Events\UserCreated;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Common\Domain\ValueObject\Email;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * @property Collection|Campaign[] campaigns
 * @property int id
 * @property DateTime|null email_confirmed_at
 * @property string uuid
 * @property string referral_id
 * @property int|null referrer_user_id
 * @mixin Builder
 */
class User extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;

    use AutomateMutators;
    use BinHex;

    public static $rules = [
        'email' => 'email|max:150|unique:users',
        'password' => 'min:8',
        'password_new' => 'min:8',
        'is_advertiser' => 'boolean',
        'is_publisher' => 'boolean',
    ];

    public static $rules_add = [
        'email' => 'required|email|max:150|unique:users',
        'password' => 'required|min:8',
        'is_advertiser' => 'boolean',
        'is_publisher' => 'boolean',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'deleted_at',
        'email_confirmed_at',
    ];

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
        'created' => UserCreated::class,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'is_advertiser',
        'is_publisher',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $visible = [
        'id',
        'uuid',
        'email',
        'name',
        'is_advertiser',
        'is_publisher',
        'is_admin',
        'api_token',
        'is_email_confirmed',
        'adserver_wallet',
        'referral_id',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];

    protected $appends = [
        'adserver_wallet',
        'is_email_confirmed',
        'referral_id',
    ];

    public static function register(array $data): User
    {
        $user = new User($data);
        $user->password = $data['password'];
        $user->email = $data['email'];
        $user->is_advertiser = true;
        $user->is_publisher = true;

        if (array_key_exists('referral_id', $data) && is_string($data['referral_id'])) {
            $userReferrer = self::fetchByUuid(bin2hex(Utils::urlSafeBase64Decode($data['referral_id'])));

            if (null !== $userReferrer) {
                $user->referrer_user_id = $userReferrer->id;
            }
        }

        $user->save();

        return $user;
    }

    public function getIsEmailConfirmedAttribute(): bool
    {
        return null !== $this->email_confirmed_at;
    }

    public function getAdserverWalletAttribute(): array
    {
        return [
            'total_funds' => $this->getBalance(),
            'wallet_balance' => $this->getWalletBalance(),
            'bonus_balance' => $this->getBonusBalance(),
            'total_funds_in_currency' => 0,
            'total_funds_change' => 0,
            'last_payment_at' => 0,
        ];
    }

    public function getReferralIdAttribute(): string
    {
        return Utils::urlSafeBase64Encode(hex2bin($this->uuid));
    }

    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = null !== $value ? Hash::make($value) : null;
    }

    public function validPassword($value): bool
    {
        return Hash::check($value, $this->attributes['password']);
    }

    public function generateApiKey(): void
    {
        if ($this->api_token) {
            return;
        }

        do {
            $this->api_token = str_random(60);
        } while ($this->where('api_token', $this->api_token)->exists());

        $this->save();
    }

    public function clearApiKey(): void
    {
        $this->api_token = null;
        $this->save();
    }

    public static function fetchByUuid(string $uuid): ?self
    {
        return self::where('uuid', hex2bin($uuid))->first();
    }

    public static function fetchByEmail(string $email): ?self
    {
        return self::where('email', $email)->first();
    }

    public function isAdvertiser(): bool
    {
        return (bool)$this->is_advertiser;
    }

    public function isPublisher(): bool
    {
        return (bool)$this->is_publisher;
    }

    public function isAdmin(): bool
    {
        return (bool)$this->is_admin;
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function getBalance(): int
    {
        return UserLedgerEntry::getBalanceByUserId($this->id);
    }

    public function getWalletBalance(): int
    {
        return UserLedgerEntry::getWalletBalanceByUserId($this->id);
    }

    public function getBonusBalance(): int
    {
        return UserLedgerEntry::getBonusBalanceByUserId($this->id);
    }

    public static function createAdmin(Email $email, string $name, string $password): void
    {
        $user = new self();
        $user->name = $name;
        $user->email = $email->toString();
        $user->confirmEmail();
        $user->password = $password;
        $user->is_admin = 1;

        $user->save();
    }

    public function awardBonus(int $amount): void
    {
        UserLedgerEntry::awardBonusToUser($this, $amount);
    }

    public function confirmEmail(): void
    {
        $this->email_confirmed_at = new DateTime();
    }
}
