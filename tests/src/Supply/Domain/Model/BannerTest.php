<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Test\Supply\Domain\Model;

use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\ValueObject\BannerUrl;
use Adshares\Supply\Domain\ValueObject\Budget;
use Adshares\Supply\Domain\ValueObject\CampaignDate;
use Adshares\Supply\Domain\ValueObject\Classification;
use Adshares\Supply\Domain\ValueObject\Exception\UnsupportedBannerTypeException;
use Adshares\Supply\Domain\ValueObject\SourceCampaign;
use Adshares\Supply\Domain\ValueObject\Status;
use DateTime;
use Exception;
use PHPUnit\Framework\TestCase;

use function uniqid;

final class BannerTest extends TestCase
{
    private const INVALID_TYPE = false;
    protected const VALID_TYPE = true;

    /**
     * @param string $type
     * @param bool $valid
     *
     * @throws Exception
     *
     * @dataProvider dataProvider
     */
    public function testWhenTypeIsInvalid(string $type, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(UnsupportedBannerTypeException::class);
        }

        $campaign = new Campaign(
            Uuid::v4(),
            UUid::fromString('4a27f6a938254573abe47810a0b03748'),
            'http://example.com',
            new CampaignDate(new DateTime(), (new DateTime())->modify('+1 hour'), new DateTime(), new DateTime()),
            [],
            new Budget(1000000000000, null, 200000000000),
            new SourceCampaign('localhost', '0000-00000000-0001', '0.1', new DateTime(), new DateTime()),
            Status::processing(),
            [],
            []
        );

        $checksum = '';
        $bannerUrl = new BannerUrl('http://example.com', 'http://example.com', 'http://example.com');
        $banner = new Banner(
            $campaign,
            Uuid::v4(),
            Uuid::v4(),
            $bannerUrl,
            $type,
            '728x90',
            $checksum,
            Status::active()
        );

        $this->assertEquals($type, $banner->getType());
    }

    public function testToArray(): void
    {
        $campaignId = Uuid::v4();
        $campaign = new Campaign(
            $campaignId,
            UUid::fromString('4a27f6a938254573abe47810a0b03748'),
            'http://example.com',
            new CampaignDate(new DateTime(), (new DateTime())->modify('+1 hour'), new DateTime(), new DateTime()),
            [],
            new Budget(1000000000000, null, 200000000000),
            new SourceCampaign('localhost', '0000-00000000-0001', '0.1', new DateTime(), new DateTime()),
            Status::processing(),
            [],
            []
        );

        $bannerId = Uuid::v4();
        $demandBannerId = Uuid::v4();
        $type = 'html';
        $checksum = uniqid('', true);
        $bannerUrl = new BannerUrl(
            'http://example.com/serve',
            'http://example.com/click',
            'http://example.com/view'
        );

        $banner = new Banner(
            $campaign,
            $bannerId,
            $demandBannerId,
            $bannerUrl,
            $type,
            '728x90',
            $checksum,
            Status::active()
        );

        $expected = [
            'id' => $bannerId,
            'demand_banner_id' => $demandBannerId,
            'type' => 'html',
            'size' => '728x90',
            'checksum' => $checksum,
            'serve_url' => 'http://example.com/serve',
            'click_url' => 'http://example.com/click',
            'view_url' => 'http://example.com/view',
            'status' => Status::STATUS_ACTIVE,
            'classification' => [],
        ];

        $this->assertEquals($expected, $banner->toArray());
        $this->assertEquals('html', $banner->getType());
        $this->assertEquals($bannerId, $banner->getId());
        $this->assertEquals($demandBannerId, $banner->getDemandBannerId());
        $this->assertEquals('728x90', $banner->getSize());
        $this->assertEquals($campaignId, $banner->getCampaignId());
    }

    public function testClassifications(): void
    {
        $campaign = $this->createCampaign();
        $banner = $this->createBanner($campaign);

        $classifier1 = 'classify1';
        $keyword1a = '1:1:1';
        $keyword1b = '1:2:0';
        $classifier2 = 'classify2';
        $keyword2 = '2:3:1';

        $classification1 = new Classification($classifier1, [$keyword1a, $keyword1b]);
        $classification2 = new Classification($classifier2, [$keyword2]);

        // CLASSIFY
        $banner->classify($classification1);
        $banner->classify($classification2);

        $expected = [
            $classifier1 => [
                $keyword1a,
                $keyword1b,
            ],
            $classifier2 => [
                $keyword2,
            ],
        ];

        $this->assertEquals($expected, $banner->toArray()['classification']);

        // REMOVE CLASSIFICATION
        $banner->removeClassification($classification2);
        unset($expected[$classifier2]);

        $this->assertEquals($expected, $banner->toArray()['classification']);

        // CLEAR CLASSIFICATION
        $banner->unclassified();

        $this->assertCount(0, $banner->toArray()['classification']);
    }

    private function createCampaign(): Campaign
    {
        $campaignId = Uuid::v4();
        $campaign = new Campaign(
            $campaignId,
            UUid::fromString('4a27f6a938254573abe47810a0b03748'),
            'http://example.com',
            new CampaignDate(new DateTime(), (new DateTime())->modify('+1 hour'), new DateTime(), new DateTime()),
            [],
            new Budget(1000000000000, null, 200000000000),
            new SourceCampaign('localhost', '0000-00000000-0001', '0.1', new DateTime(), new DateTime()),
            Status::active(),
            [],
            []
        );

        return $campaign;
    }

    private function createBanner(Campaign $campaign, string $size = '300x250'): Banner
    {
        $url = new BannerUrl('http://example.com/serve', 'http://example.com/click', 'http://example.com/view');
        $banner = new Banner(
            $campaign,
            Uuid::v4(),
            Uuid::v4(),
            $url,
            'image',
            $size,
            '',
            Status::active()
        );

        return $banner;
    }

    public function dataProvider(): array
    {
        return [
            ['unsupported_type', self::INVALID_TYPE],
            ['html', self::VALID_TYPE],
            ['image', self::VALID_TYPE],
        ];
    }
}
