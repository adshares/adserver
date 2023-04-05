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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Services\Common;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Services\Common\AdsTxtCrawler;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

final class AdsTxtCrawlerTest extends TestCase
{
    public function testCheckSite(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['uuid' => 'c44a2e70bcdc46658fd94337da124032']);
        $site = Site::factory()->create(['user_id' => $user]);
        Http::preventStrayRequests();
        Http::fake([
            'example.com/ads.txt' => Http::response(
                sprintf(
                    <<<ADS_TXT
# ads.txt file for example.com

ads.com, pub-284735058564, RESELLER
adshares.net, %s, DIRECT
example.com, pub-124735058564, DIRECT, nrseyvor5e65
ADS_TXT
                    ,
                    Uuid::fromString($user->uuid)->toString()
                )
            ),
        ]);
        Config::updateAdminSettings([
            Config::ADS_TXT_CRAWLER_ENABLED => '1',
            Config::URL => 'https://app.adshares.net',
        ]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $adsTxtCrawler = new AdsTxtCrawler();

        self::assertTrue($adsTxtCrawler->checkSite($site));
    }

    public function testCheckSiteWhileNotFound(): void
    {
        $user = User::factory()->create(['uuid' => 'c44a2e70bcdc46658fd94337da124032']);
        $site = Site::factory()->create(['user_id' => $user]);
        Http::preventStrayRequests();
        Http::fake([
            'example.com/ads.txt' => Http::response('Not found', Response::HTTP_NOT_FOUND),
        ]);
        Config::updateAdminSettings([Config::ADS_TXT_CRAWLER_ENABLED => '1']);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $adsTxtCrawler = new AdsTxtCrawler();

        self::assertFalse($adsTxtCrawler->checkSite($site));
    }

    public function testCheckSiteWhileConnectionException(): void
    {
        $user = User::factory()->create(['uuid' => 'c44a2e70bcdc46658fd94337da124032']);
        $site = Site::factory()->create(['user_id' => $user]);
        Http::preventStrayRequests();
        Http::fake(fn() => throw new ConnectionException('test-exception'));
        Config::updateAdminSettings([Config::ADS_TXT_CRAWLER_ENABLED => '1']);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $adsTxtCrawler = new AdsTxtCrawler();

        self::assertFalse($adsTxtCrawler->checkSite($site));
    }

    public function testCheckSites(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['uuid' => 'c44a2e70bcdc46658fd94337da124032']);
        /** @var Site $siteExample */
        $siteExample = Site::factory()->create(['url' => 'https://example.com', 'user_id' => $user]);
        /** @var Site $siteExample2 */
        $siteExample2 = Site::factory()->create(['url' => 'https://example2.com', 'user_id' => $user]);
        Http::preventStrayRequests();
        Http::fake([
            'example.com/ads.txt' => Http::response(
                sprintf(
                    <<<ADS_TXT
# ads.txt file for example.com

ads.com, pub-284735058564, RESELLER
adshares.net, %s, DIRECT
example.com, pub-124735058564, DIRECT, nrseyvor5e65
ADS_TXT
                    ,
                    Uuid::fromString($user->uuid)->toString()
                )
            ),
            'example2.com/ads.txt' => Http::response(
                <<<ADS_TXT
# ads.txt file for example2.com

ads.com, pub-284735058564, RESELLER
example.com, pub-124735058564, DIRECT, nrseyvor5e65
ADS_TXT
            ),
        ]);
        Config::updateAdminSettings([
            Config::ADS_TXT_CRAWLER_ENABLED => '1',
            Config::URL => 'https://app.adshares.net',
        ]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $sites = Site::all();
        $adsTxtCrawler = new AdsTxtCrawler();

        $result = $adsTxtCrawler->checkSites($sites);

        self::assertTrue($result[$siteExample->id]);
        self::assertFalse($result[$siteExample2->id]);
    }
}
