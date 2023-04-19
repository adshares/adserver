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

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Events\UserCreated;
use Adshares\Adserver\Models\Traits\AddressWithNetwork;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Adserver\ViewModel\Role;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Config\UserRole;
use DateTime;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property Collection|Campaign[] campaigns
 * @property int id
 * @property string email
 * @property string label
 * @property Carbon|null created_at
 * @property Carbon|null updated_at
 * @property Carbon|null deleted_at
 * @property DateTime|null email_confirmed_at
 * @property DateTime|null admin_confirmed_at
 * @property string uuid
 * @property int|null ref_link_id
 * @property RefLink|null refLink
 * @property string|null name
 * @property string|null password
 * @property string|null api_token
 * @property int subscribe
 * @property bool is_email_confirmed
 * @property bool is_admin_confirmed
 * @property bool is_confirmed
 * @property bool is_admin
 * @property bool is_moderator
 * @property bool is_agency
 * @property int is_advertiser
 * @property int is_publisher
 * @property WalletAddress|null wallet_address
 * @property int|null auto_withdrawal
 * @property bool is_auto_withdrawal
 * @property int auto_withdrawal_limit
 * @property int is_banned
 * @property string ban_reason
 * @property Carbon|null last_active_at
 * @property int invalid_login_attempts
 * @property array roles
 * @mixin Builder
 */
class User extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;

    use AutomateMutators;
    use BinHex;
    use AddressWithNetwork;
    use HasApiTokens;
    use HasFactory;
    use LogsActivity;

    public static $rules_add = [
        'email' => 'required|email|max:150|unique:users',
        'password' => 'required|min:8',
        'is_advertiser' => 'boolean',
        'is_publisher' => 'boolean',
    ];
    private const DATE_CAST = 'date:' . DateTimeInterface::ATOM;

    protected $casts = [
        'created_at' => self::DATE_CAST,
        'updated_at' => self::DATE_CAST,
        'deleted_at' => self::DATE_CAST,
        'admin_confirmed_at' => self::DATE_CAST,
        'email_confirmed_at' => self::DATE_CAST,
        'last_active_at' => self::DATE_CAST,
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'deleted_at',
        'email_confirmed_at',
        'admin_confirmed_at',
        'last_active_at',
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
        'email',
        'wallet_address',
        'auto_withdrawal',
        'is_advertiser',
        'is_publisher',
    ];

    protected $visible = [
        'id',
        'uuid',
        'email',
        'name',
        'has_password',
        /** @deprecated use roles instead */
        'is_advertiser',
        /** @deprecated use roles instead */
        'is_publisher',
        /** @deprecated use roles instead */
        'is_admin',
        /** @deprecated use roles instead */
        'is_moderator',
        /** @deprecated use roles instead */
        'is_agency',
        'api_token',
        'is_email_confirmed',
        'is_admin_confirmed',
        'is_confirmed',
        'is_subscribed',
        'adserver_wallet',
        'is_banned',
        'ban_reason',
        'roles',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
        'wallet_address' => 'AddressWithNetwork'
    ];

    protected $appends = [
        'has_password',
        'adserver_wallet',
        'is_email_confirmed',
        'is_admin_confirmed',
        'is_confirmed',
        'is_subscribed',
        'roles',
    ];

    protected function toArrayExtras($array)
    {
        if (null === $array['email']) {
            $array['email'] = (string)$array['wallet_address'];
        }
        unset($array['wallet_address']);
        return $array;
    }

    public function getLabelAttribute(): string
    {
        return '#' . $this->id . (null !== $this->email ? ' (' . $this->email . ')' : '');
    }

    public function getHasPasswordAttribute(): bool
    {
        return null !== $this->password;
    }

    public function getIsEmailConfirmedAttribute(): bool
    {
        return null !== $this->email_confirmed_at;
    }

    public function getIsAdminConfirmedAttribute(): bool
    {
        return null !== $this->admin_confirmed_at;
    }

    public function getIsConfirmedAttribute(): bool
    {
        return (null === $this->email || $this->is_email_confirmed) && $this->is_admin_confirmed;
    }

    public function getIsSubscribedAttribute(): bool
    {
        return 0 !== $this->subscribe;
    }

    public function getAdserverWalletAttribute(): array
    {
        return [
            'total_funds' => $this->getBalance(),
            'wallet_balance' => $this->getWalletBalance(),
            'bonus_balance' => $this->getBonusBalance(),
            'withdrawable_balance' => $this->getWithdrawableBalance(),
            'total_funds_in_currency' => 0,
            'total_funds_change' => 0,
            'last_payment_at' => 0,
            'wallet_address' => optional($this->wallet_address)->getAddress(),
            'wallet_network' => optional($this->wallet_address)->getNetwork(),
            'is_auto_withdrawal' => $this->is_auto_withdrawal,
            'auto_withdrawal_limit' => $this->auto_withdrawal_limit,
        ];
    }

    public function getIsAutoWithdrawalAttribute(): bool
    {
        return null !== $this->auto_withdrawal;
    }

    public function getAutoWithdrawalLimitAttribute(): int
    {
        return (int)$this->auto_withdrawal;
    }

    public function getRolesAttribute(): array
    {
        $roles = [
            Role::Admin->value => $this->isAdmin(),
            Role::Advertiser->value => $this->isAdvertiser(),
            Role::Agency->value => $this->isAgency(),
            Role::Moderator->value => $this->isModerator(),
            Role::Publisher->value => $this->isPublisher(),
        ];

        return array_keys(array_filter($roles));
    }

    public function setRolesAttribute($value): void
    {
        $this->is_admin = in_array(Role::Admin->value, $value);
        $this->is_advertiser = in_array(Role::Advertiser->value, $value);
        $this->is_agency = in_array(Role::Agency->value, $value);
        $this->is_moderator = in_array(Role::Moderator->value, $value);
        $this->is_publisher = in_array(Role::Publisher->value, $value);
    }

    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = null !== $value ? Hash::make($value) : null;
    }

    public function setHashedPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = $value;
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
            $this->api_token = Str::random(60);
        } while ($this->where('api_token', $this->api_token)->exists());

        $this->last_active_at = now();
        $this->invalid_login_attempts = 0;
        $this->save();
    }

    public function clearApiKey(): void
    {
        $this->api_token = null;
        $this->save();
    }

    public function maskEmailAndWalletAddress(): void
    {
        $this->email = sprintf('%s@%s', $this->uuid, DomainReader::domain(config('app.url')));
        $this->email_confirmed_at = null;
        $this->wallet_address = null;
        $this->save();
    }

    public function ban(string $reason): void
    {
        $this->is_banned = 1;
        $this->ban_reason = $reason;
        $this->api_token = null;
        $this->auto_withdrawal = null;
        $this->save();
    }

    public function unban(): void
    {
        $this->is_banned = 0;
        $this->save();
    }

    public static function fetchById(int $id): ?self
    {
        return self::find($id);
    }

    public static function fetchByIds(array $ids): Collection
    {
        return self::whereIn('id', $ids)->get()->keyBy('id');
    }

    public static function fetchByUuid(string $uuid): ?self
    {
        return self::where('uuid', hex2bin($uuid))->first();
    }

    public static function fetchByEmail(string $email): ?self
    {
        return self::where('email', $email)->first();
    }

    public static function fetchByWalletAddress(WalletAddress $address): ?self
    {
        return self::where('wallet_address', $address)->first();
    }

    public static function findByAutoWithdrawal(): Collection
    {
        return self::whereNotNull('auto_withdrawal')->get();
    }

    public static function fetchByForeignWalletAddress(string $address): ?self
    {
        return self::where('foreign_wallet_address', $address)->first();
    }

    public static function generateRandomETHWallet(): string {
        // An eth address contains 40 hexadecimals. 
        
        return '0x' . substr( hash('sha256', strval(rand(1,1000000) * microtime(true)) ), 0, 40); 
    }

    public function isAdvertiser(): bool
    {
        return (bool)$this->is_advertiser || $this->isModerator() || $this->isAdmin();
    }

    public function isPublisher(): bool
    {
        return (bool)$this->is_publisher || $this->isModerator() || $this->isAdmin();
    }

    public function isAdmin(): bool
    {
        return (bool)$this->is_admin;
    }

    public function isModerator(): bool
    {
        return (bool)$this->is_moderator || (bool)$this->is_admin;
    }

    public function isAgency(): bool
    {
        return (bool)$this->is_agency;
    }

    public function isRegular(): bool
    {
        return !$this->isAdmin() && !$this->isModerator() && !$this->isAgency();
    }

    public function isBanned(): bool
    {
        return (bool)$this->is_banned;
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function uploadedFiles(): HasMany
    {
        return $this->hasMany(UploadedFile::class);
    }

    public function getBalance(): int
    {
        return UserLedgerEntry::getBalanceByUserId($this->id);
    }

    public function getWalletBalance(): int
    {
        return UserLedgerEntry::getWalletBalanceByUserId($this->id);
    }

    public function getWithdrawableBalance(): int
    {
        return UserLedgerEntry::getWithdrawableBalanceByUserId($this->id);
    }

    public function getBonusBalance(): int
    {
        return UserLedgerEntry::getBonusBalanceByUserId($this->id);
    }

    public function getRefundBalance(): int
    {
        return $this->getBonusBalance();
    }

    public function refLink(): BelongsTo
    {
        return $this->belongsTo(RefLink::class);
    }

    public function getReferrals(): Collection
    {
        return self::has('refLink')->get();
    }

    public function getReferralIds(): array
    {
        return $this->getReferrals()->pluck('id')->toArray();
    }

    public function getReferralUuids(): array
    {
        return $this->getReferrals()->pluck('uuid')->toArray();
    }

    public static function registerWithEmail(string $email, string $password, ?RefLink $refLink = null): User
    {
        return self::register(
            array_merge(
                [
                    'email' => $email,
                    'password' => $password,
                ],
                self::getUserRoles()
            ),
            $refLink
        );
    }

    private static function getUserRoles(): array
    {
        $defaultUserRoles = config('app.default_user_roles');
        return [
            'is_advertiser' => in_array(UserRole::ADVERTISER, $defaultUserRoles) ? 1 : 0,
            'is_publisher' => in_array(UserRole::PUBLISHER, $defaultUserRoles) ? 1 : 0,
        ];
    }

    protected static function register(array $data, ?RefLink $refLink = null): User
    {
        $user = User::create($data);
        $user->refresh();
        $user->password = $data['password'] ?? null;
        if (null !== $refLink) {
            if (null !== $refLink->user_roles) {
                $userRoles = explode(',', $refLink->user_roles);
                $user->is_advertiser = in_array(UserRole::ADVERTISER, $userRoles) ? 1 : 0;
                $user->is_publisher = in_array(UserRole::PUBLISHER, $userRoles) ? 1 : 0;
            }
            $user->ref_link_id = $refLink->id;
            $refLink->used = true;
            $refLink->saveOrFail();
        }
        $user->saveOrFail();
        return $user;
    }

    public function updateEmailWalletAndRoles(
        ?string $email = null,
        ?WalletAddress $walletAddress = null,
        ?array $roles = null,
    ): void {
        if (null !== $email) {
            $this->email = $email;
        }
        if (null !== $walletAddress) {
            $this->wallet_address = $walletAddress;
        }
        if (null !== $roles) {
            $this->roles = $roles;
        }
        $this->saveOrFail();
    }

    public static function registerWithWallet(
        WalletAddress $address,
        bool $autoWithdrawal = false,
        ?RefLink $refLink = null
    ): User {
        return self::register(
            array_merge(
                [
                    'wallet_address' => $address,
                    'auto_withdrawal' => $autoWithdrawal
                        ? config('app.auto_withdrawal_limit_' . strtolower($address->getNetwork()))
                        : null,
                ],
                self::getUserRoles()
            ),
            $refLink
        );
    }

    public static function registerAdmin(string $email, string $name, string $password): User
    {
        $user = self::register([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
        $user->is_admin = true;
        $user->confirmEmail();
        $user->confirmAdmin();
        $user->saveOrFail();
        return $user;
    }

    public static function fetchOrRegisterSystemUser(): User
    {
        if (null !== ($user = self::where('name', 'system')->first())) {
            return $user;
        }

        $user = new User();
        $user->name = 'system';
        $user->password = Str::random(32);
        $user->is_advertiser = 0;
        $user->is_publisher = 0;
        $user->saveOrFail();
        return $user;
    }

    public function awardBonus(int $amount, ?RefLink $refLink = null): void
    {
        UserLedgerEntry::insertUserBonus($this->id, $amount, $refLink);
    }

    public function confirmEmail(): void
    {
        $this->email_confirmed_at = new DateTime();
        $this->subscription(true);
    }

    public function confirmAdmin(): void
    {
        $this->admin_confirmed_at = new DateTime();
    }

    public function subscription(bool $subscribe): void
    {
        $this->subscribe = $subscribe ? 1 : 0;
    }

    public static function fetchEmails(): Collection
    {
        return self::where('subscribe', 1)->whereNotNull('email')->get()->pluck('email');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logAll()
            ->logExcept(['api_token', 'last_active_at', 'updated_at'])
            ->logOnlyDirty()
            ->useAttributeRawValues(['wallet_address'])
            ->dontSubmitEmptyLogs();
    }
}
