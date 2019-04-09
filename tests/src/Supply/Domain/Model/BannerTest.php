<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
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
use Adshares\Supply\Domain\ValueObject\Exception\UnsupportedBannerSizeException;
use Adshares\Supply\Domain\ValueObject\Size;
use Adshares\Supply\Domain\ValueObject\SourceCampaign;
use Adshares\Supply\Domain\ValueObject\Status;
use DateTime;
use PHPUnit\Framework\TestCase;
use function uniqid;

final class BannerTest extends TestCase
{
    const INVALID_TYPE = false;
    const VALID_TYPE = true;

    /**
     * @param string $type
     * @param bool $valid
     *
     * @throws \Exception
     *
     * @dataProvider dataProvider
     */
    public function testWhenTypeIsInvalid(string $type, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(UnsupportedBannerSizeException::class);
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
        $banner = new Banner($campaign, Uuid::v4(), $bannerUrl, $type, new Size(728, 90), $checksum, Status::active());

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
        $type = Banner::HTML_TYPE;
        $checksum = uniqid('', true);
        $bannerUrl = new BannerUrl(
            'http://example.com/serve',
            'http://example.com/click',
            'http://example.com/view'
        );

        $banner = new Banner($campaign, $bannerId, $bannerUrl, $type, new Size(728, 90), $checksum, Status::active());

        $expected = [
            'id' => $bannerId,
            'type' => 'html',
            'size' => '728x90',
            'width' => 728,
            'height' => 90,
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
        $this->assertEquals(728, $banner->getWidth());
        $this->assertEquals(90, $banner->getHeight());
        $this->assertEquals('728x90', $banner->getSize());
        $this->assertEquals($campaignId, $banner->getCampaignId());
    }

    public function testClassifications(): void
    {
        $campaign = $this->createCampaign();
        $banner = $this->createBanner($campaign);

        $keyword1 = 'classify:1:1:1';
        $keyword2 = 'classify:1:2:0';
        $keyword3 = 'classify:2:3:1';

        $classification1 = new Classification($keyword1, 'signature#1');
        $classification2 = new Classification($keyword2, 'signature#2');
        $classification3 = new Classification($keyword3, 'signature#3');

        // CLASSIFY
        $banner->classify($classification1);
        $banner->classify($classification2);
        $banner->classify($classification3);

        $expected = [
            [
                'keyword' => $keyword1,
                'signature' => 'signature#1',
            ],
            [
                'keyword' => $keyword2,
                'signature' => 'signature#2',
            ],
            [
                'keyword' => $keyword3,
                'signature' => 'signature#3',
            ],
        ];

        $this->assertEquals($expected, $banner->toArray()['classification']);

        // REMOVE CLASSIFICATION
        $banner->removeClassification($classification2);
        unset($expected[1]);

        $this->assertEquals(array_values($expected), $banner->toArray()['classification']);

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

    private function createBanner(Campaign $campaign, int $width = 300, int $height = 250): Banner
    {
        $url = new BannerUrl('http://example.com/serve', 'http://example.com/click', 'http://example.com/view');
        $banner = new Banner(
            $campaign,
            Uuid::v4(),
            $url,
            'image',
            new Size($width, $height),
            '',
            Status::active()
        );

        return $banner;
    }

    public function dataProvider()
    {
        return [
            ['unsupported_type', self::INVALID_TYPE],
            ['html', self::VALID_TYPE],
            ['image', self::VALID_TYPE],
        ];
    }
}
