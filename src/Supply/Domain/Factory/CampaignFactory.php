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

declare(strict_types=1);

namespace Adshares\Supply\Domain\Factory;

use Adshares\Common\Domain\Adapter\ArrayCollection;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Domain\Factory\Exception\InvalidCampaignArgumentException;
use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\ValueObject\BannerUrl;
use Adshares\Supply\Domain\ValueObject\Budget;
use Adshares\Supply\Domain\ValueObject\CampaignDate;
use Adshares\Supply\Domain\ValueObject\Size;
use Adshares\Supply\Domain\ValueObject\SourceCampaign;
use Adshares\Supply\Domain\ValueObject\Status;
use function array_key_exists;

class CampaignFactory
{
    public static function createFromArray(array $data): Campaign
    {

        self::validateArrayParameters($data);

        $source = $data['source_campaign'];
        $sourceHost = new SourceCampaign(
            $source['host'],
            $source['address'],
            $source['version'],
            $source['created_at'],
            $source['updated_at'] ?? null
        );

        $budget = new Budget($data['budget'], $data['max_cpc'], $data['max_cpm']);

        $arrayBanners = $data['banners'];
        $banners = [];

        $campaign = new Campaign(
            $data['uuid'] ?? Uuid::v4(),
            $data['uuid'],
            $data['publisher_id'],
            $data['landing_url'],
            new CampaignDate($data['date_start'], $data['date_end'], $data['created_at'], $data['updated_at']),
            $banners,
            $budget,
            $sourceHost,
            isset($data['status']) ? Status::fromStatus($data['status']) : Status::processing(),
            $data['targeting_requires'],
            $data['targeting_excludes']
        );

        foreach ($arrayBanners as $banner) {
            $bannerUrl = new BannerUrl($banner['serve_url'], $banner['click_url'], $banner['view_url']);
            $size = new Size($banner['width'], $banner['height']);
            $id = isset($banner['uuid']) ? Uuid::fromString($banner['uuid']) : Uuid::v4();
            $status = isset($banner['status']) ? Status::fromStatus($banner['status']) : Status::processing();

            $banners[] = new Banner($campaign, $id, $bannerUrl, $banner['type'], $size, '', $status);
        }

        $campaign->setBanners(new ArrayCollection($banners));

        return $campaign;
    }

    private static function validateArrayParameters(array $data): void
    {
        $pattern = [
            'source_campaign' => ['host', 'address', 'version', 'created_at', 'updated_at'],
            'created_at',
            'updated_at',
            'budget',
            'max_cpc',
            'max_cpm',
            'uuid',
            'publisher_id',
            'landing_url',
            'date_start',
            'date_end',
            'created_at',
            'updated_at',
            'targeting_requires',
            'targeting_excludes',
        ];

        foreach ($pattern as $key => $value) {
            if (is_array($value)) {
                $nestedPatternKeys = array_values($value);
                $nestedDataKeys = array_keys($data[$key]);

                $diff = array_diff($nestedPatternKeys, $nestedDataKeys);

                if ($diff) {
                    throw new InvalidCampaignArgumentException(sprintf(
                        '(%s) field (%s) is missing. The field is required.',
                        implode(',', $diff),
                        $key
                    ));
                }

                continue;
            }

            if (!array_key_exists($value, $data)) {
                throw new InvalidCampaignArgumentException(sprintf(
                    '%s field is missing. THe field is required.',
                    $value
                ));
            }
        }
    }
}
