<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Services\Common;

use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\Classification;
use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserSettings;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Services\Common\Exception\UserDeletionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserDelete
{
    public function __construct(private readonly CampaignRepository $campaignRepository)
    {
    }

    public function deleteUser(User $user): void
    {
        $userId = $user->id;

        DB::beginTransaction();
        try {
            $campaigns = $this->campaignRepository->findByUserId($userId);
            foreach ($campaigns as $campaign) {
                $this->campaignRepository->delete($campaign);
            }
            BidStrategy::deleteByUserId($userId);

            $sites = $user->sites();
            foreach ($sites->get() as $site) {
                $site->zones()->delete();
            }
            $sites->delete();

            RefLink::fetchByUser($userId)->each(fn(RefLink $refLink) => $refLink->delete());
            Token::deleteByUserId($userId);
            Classification::deleteByUserId($userId);
            UserSettings::deleteByUserId($userId);

            $user->maskEmailAndWalletAddress();
            $user->clearApiKey();
            $user->delete();

            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error(sprintf('Exception during user deletion: (%s)', $throwable->getMessage()));
            throw new UserDeletionException();
        }
    }
}
