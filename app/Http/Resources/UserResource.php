<?php

namespace Adshares\Adserver\Http\Resources;

use Adshares\Adserver\Models\User;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var User $this */
        return [
            'id' => $this->id,
            'email' => $this->email,
            'adminConfirmed' => $this->is_admin_confirmed,
            'emailConfirmed' => $this->is_email_confirmed,
            'adsharesWallet' => [
                'walletBalance' => null !== $this->wallet_balance
                    ? (int)$this->wallet_balance : $this->getWalletBalance(),
                'bonusBalance' => null !== $this->bonus_balance
                    ? (int)$this->bonus_balance : $this->getBonusBalance(),
            ],
            'connectedWallet' => [
                'address' => $this->wallet_address?->getAddress(),
                'network' => $this->wallet_address?->getNetwork(),
            ],
            'roles' => $this->roles,
            'campaignCount' => null !== $this->campaign_count
                ? (int)$this->campaign_count : $this->campaigns()->count(),
            'siteCount' => null !== $this->site_count
                ? (int)$this->site_count : $this->sites()->count(),
            'lastActiveAt' => $this->last_active_at?->format(DateTimeInterface::ATOM),
        ];
    }
}
