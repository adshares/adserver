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

namespace Adshares\Adserver\Tests\Mail\Crm;

use Adshares\Adserver\Mail\Crm\CampaignCreated;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Tests\Mail\MailTestCase;

class CampaignCreatedTest extends MailTestCase
{
    public function testBuild(): void
    {
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create([
            'budget' => 123_000_000_000,
            'landing_url' => 'https://example.com/1234',
            'name' => 'Test campaign',
            'time_start' => '2023-04-06T03:48:11+00:00',
        ]);

        $user = $campaign->user;
        $mailable = new CampaignCreated($user->uuid, $user->email, $campaign);

        $mailable->assertSeeInHtml(sprintf('userId=%s', $user->uuid));
        $mailable->assertSeeInHtml(sprintf('email=%s', $user->email));
        $mailable->assertSeeInHtml('adserverName=AdServer');
        $mailable->assertSeeInHtml('adserverId=');
        $mailable->assertSeeInHtml('campaignName=Test campaign');
        $mailable->assertSeeInHtml('targetUrl=https://example.com/1234');
        $mailable->assertSeeInHtml('budget=29.52');//24 * 1.23
        $mailable->assertSeeInHtml('startDate=06/04/2023 03:48:11');
        $mailable->assertSeeInHtml('endDate=');
        $mailable->assertSeeInHtml('advertiser=true');
    }
}
