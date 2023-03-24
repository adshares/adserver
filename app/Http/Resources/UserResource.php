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
                'withdrawableBalance' => null !== $this->withdrawable_balance
                    ? (int)$this->withdrawable_balance : $this->getWithdrawableBalance(),
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
            'isBanned' => $this->isBanned(),
            'banReason' => $this->ban_reason,
        ];
    }
}
