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

declare(strict_types=1);

namespace Adshares\Adserver\Services\Common;

use Adshares\Adserver\Mail\Crm\CampaignCreated;
use Adshares\Adserver\Mail\Crm\SiteAdded;
use Adshares\Adserver\Mail\Crm\UserRegistered;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\Mail;

class CrmNotifier
{
    public static function sendCrmMailOnCampaignCreated(User $user, Campaign $campaign): void
    {
        if (config('app.crm_mail_address_on_campaign_created')) {
            Mail::to(config('app.crm_mail_address_on_campaign_created'))->queue(
                new CampaignCreated($user->uuid, $user->email, $campaign)
            );
        }
    }

    public static function sendCrmMailOnSiteAdded(User $user, Site $site): void
    {
        if (config('app.crm_mail_address_on_site_added')) {
            Mail::to(config('app.crm_mail_address_on_site_added'))->queue(
                new SiteAdded($user->uuid, $user->email, $site)
            );
        }
    }

    public static function sendCrmMailOnUserRegistered(User $user): void
    {
        if (config('app.crm_mail_address_on_user_registered')) {
            Mail::to(config('app.crm_mail_address_on_user_registered'))->queue(
                new UserRegistered(
                    $user->uuid,
                    $user->email,
                    ($user->created_at ?: new DateTimeImmutable())->format('d/m/Y'),
                    optional($user->refLink)->token
                )
            );
        }
    }
}
