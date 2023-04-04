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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Console\Commands\CdnUploadBannersCommand;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Services\Cdn\SkynetCdn;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionObject;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CdnUploadBannersCommandTest extends ConsoleTestCase
{
    public function testHandle(): void
    {
        $this->setUpSkynetCdn();
        $client = self::createMock(Client::class);
        $client->method('post')
            ->willReturn(new Response(body: json_encode(['skylink' => 'abcd'])));
        $this->setUpSkynetCdnClient($client);

        $campaign = Campaign::factory()->create(['status' => Campaign::STATUS_ACTIVE]);
        $banner = Banner::factory()->create(['campaign_id' => $campaign]);

        $this->artisan(CdnUploadBannersCommand::COMMAND_SIGNATURE)
            ->assertSuccessful();
        self::assertEquals('https//example.com/cdn/abcd/', $banner->refresh()->cdn_url);
    }

    public function testHandleWhileClientError(): void
    {
        $this->setUpSkynetCdn();
        $client = self::createMock(Client::class);
        $client->method('post')->willThrowException(new HttpException(500, 'test-exceptopn'));
        $this->setUpSkynetCdnClient($client);

        $campaign = Campaign::factory()->create(['status' => Campaign::STATUS_ACTIVE]);
        $banner = Banner::factory()->create(['campaign_id' => $campaign]);

        $this->artisan(CdnUploadBannersCommand::COMMAND_SIGNATURE)
            ->assertSuccessful();
        self::assertNull($banner->refresh()->cdn_url);
    }

    public function testHandleWhileNoCdnProvider(): void
    {
        $this->artisan(CdnUploadBannersCommand::COMMAND_SIGNATURE)
            ->expectsOutputToContain('There is no CDN provider')
            ->assertSuccessful();
    }

    public function testLock(): void
    {
        $lockerMock = self::createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan(CdnUploadBannersCommand::COMMAND_SIGNATURE)
            ->expectsOutput('Command ops:demand:cdn:upload already running');
    }

    private function setUpSkynetCdn(): void
    {
        Config::updateAdminSettings([
            Config::CDN_PROVIDER => 'skynet',
            Config::SKYNET_API_KEY => 'api-key',
            Config::SKYNET_API_URL => 'https//example.com/api',
            Config::SKYNET_CDN_URL => 'https//example.com/cdn',
        ]);
    }

    private function setUpSkynetCdnClient(MockObject|Client $client): void
    {
        $cdn = new SkynetCdn('', '', '');
        $cdnReflection = new ReflectionObject($cdn);
        $cdnReflection->getProperty('client')->setValue($client);
    }
}
