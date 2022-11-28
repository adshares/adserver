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

namespace Adshares\Adserver\Tests\Services\Common;

use Adshares\Adserver\Mail\Crm\CampaignCreated;
use Adshares\Adserver\Mail\Crm\SiteAdded;
use Adshares\Adserver\Mail\Crm\UserRegistered;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Services\Common\CrmNotifier;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Illuminate\Support\Facades\Mail;

final class CrmNotifierTest extends TestCase
{
    public function testSendCrmMailOnCampaignCreated(): void
    {
        Config::updateAdminSettings([Config::CRM_MAIL_ADDRESS_ON_CAMPAIGN_CREATED => 'mail@example.com']);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $user = User::factory()->create();
        $campaign = Campaign::factory()->create(['user_id' => $user->id]);

        CrmNotifier::sendCrmMailOnCampaignCreated($user, $campaign);

        Mail::assertQueued(CampaignCreated::class);
    }

    public function testSendCrmMailOnSiteAdded(): void
    {
        Config::updateAdminSettings([Config::CRM_MAIL_ADDRESS_ON_SITE_ADDED => 'mail@example.com']);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $user = User::factory()->create();
        $site = Site::factory()->create(['user_id' => $user->id]);

        CrmNotifier::sendCrmMailOnSiteAdded($user, $site);

        Mail::assertQueued(SiteAdded::class);
    }

    public function testSendCrmMailOnUserRegistered(): void
    {
        Config::updateAdminSettings([Config::CRM_MAIL_ADDRESS_ON_USER_REGISTERED => 'mail@example.com']);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $user = User::factory()->create();

        CrmNotifier::sendCrmMailOnUserRegistered($user);

        Mail::assertQueued(UserRegistered::class);
    }
}
