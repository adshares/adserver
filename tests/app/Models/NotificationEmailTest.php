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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\NotificationEmailLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\NotificationEmailCategory;

class NotificationEmailTest extends TestCase
{
    /**
     * @dataProvider fetchProvider
     */
    public function testFetch(
        NotificationEmailCategory $category,
        array $propertiesInsert,
        array $propertiesFetch,
    ): void {
        NotificationEmailLog::register(1000, $category, properties: $propertiesInsert);

        $log = NotificationEmailLog::fetch(1000, $category, $propertiesFetch);

        self::assertNotNull($log);
    }

    public function fetchProvider(): array
    {
        return [
            'CampaignAccepted' => [
                NotificationEmailCategory::CampaignAccepted,
                ['campaignId' => 1],
                ['campaignId' => 1],
            ],
            'CampaignDraft' => [
                NotificationEmailCategory::CampaignDraft,
                ['campaignId' => 1],
                ['campaignId' => 1],
            ],
            'CampaignEnded' => [
                NotificationEmailCategory::CampaignEnded,
                ['campaignId' => 1],
                ['campaignId' => 1],
            ],
            'CampaignEndedExtend' => [NotificationEmailCategory::CampaignEndedExtend, [], []],
            'CampaignEnds' => [
                NotificationEmailCategory::CampaignEnds,
                ['campaignId' => 1],
                ['campaignId' => 1],
            ],
            'FundsEnded' => [NotificationEmailCategory::FundsEnded, [], []],
            'FundsEnds' => [NotificationEmailCategory::FundsEnds, [], []],
            'InactiveUser' => [NotificationEmailCategory::InactiveUser, [], []],
            'InactiveUserExtend' => [NotificationEmailCategory::InactiveUserExtend, [], []],
            'InactiveUserWhoDeposit' => [NotificationEmailCategory::InactiveUserWhoDeposit, [], []],
            'SiteAccepted' => [
                NotificationEmailCategory::SiteAccepted,
                ['siteId' => 1],
                ['siteId' => 1],
            ],
            'SiteDraft' => [
                NotificationEmailCategory::SiteDraft,
                ['siteId' => 1],
                ['siteId' => 1],
            ],
        ];
    }

    public function testFetchWhilePropertiesAreIrrelevant(): void {
        /** @var User $user */
        $user = User::factory()->create();
        NotificationEmailLog::factory()->create([
            'category' => NotificationEmailCategory::InactiveUser,
            'properties' => ['dummy' => 1],
            'user_id' => $user,
        ]);

        $log = NotificationEmailLog::fetch($user->id, NotificationEmailCategory::InactiveUser);

        self::assertNotNull($log);
    }
}
