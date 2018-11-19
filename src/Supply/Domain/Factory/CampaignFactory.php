<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Supply\Domain\Factory;

use Adshares\Common\Domain\Adapter\ArrayCollection;
use Adshares\Supply\Domain\Factory\Exception\InvalidCampaignArgumentException;
use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\ValueObject\BannerUrl;
use Adshares\Supply\Domain\ValueObject\Budget;
use Adshares\Supply\Domain\ValueObject\Size;
use Adshares\Supply\Domain\ValueObject\SourceHost;

class CampaignFactory
{
    public static function createFromArray(array $data): Campaign
    {
        self::validateArrayParameters($data);

        $source = $data['source_host'];

        $budget = new Budget($data['budget'], $data['max_cpc'], $data['max_cpm']);
        $sourceHost = new SourceHost(
            $source['host'],
            $source['address'],
            $source['created_at'],
            $source['updated_at'],
            $source['version']
        );

        $arrayBanners = $data['banners'];
        $banners = [];

        $campaign = new Campaign(
            $data['uuid'],
            $data['user_id'],
            $data['landing_url'],
            $data['date_start'],
            $data['date_end'],
            $banners,
            $budget,
            $sourceHost,
            Campaign::STATUS_PROCESSING,
            $data['targeting_requires'],
            $data['targeting_excludes']
        );

        foreach ($arrayBanners as $banner) {
            $bannerUrl = new BannerUrl($banner['serve_url'], $banner['click_url'], $banner['view_url']);
            $size = new Size($banner['width'], $banner['height']);

            $banners[] = new Banner($campaign, $bannerUrl, $banner['type'], $size);
        }

        $campaign->setBanners(new ArrayCollection($banners));

        return $campaign;
    }

    private static function validateArrayParameters(array $data): void
    {
        $pattern = [
            'source_host' => [
                'host' => '',
                'address' => '',
                'created_at' => '',
                'updated_at' => '',
                'version' => '',
            ],
            'budget',
            'max_cpc',
            'max_cpm',
            'uuid',
            'user_id',
            'landing_url',
            'date_start',
            'date_end',
            'targeting_requires',
            'targeting_excludes',
            'banners' => [
                'server_url' => '',
                'click_url' => '',
                'view_url' => '',
                'width' => '',
                'height' => '',
                'type' => '',
            ],
        ];

        $checkAllKeys = function (array $pattern, $parent = null) use (&$checkAllKeys, $data) {
            foreach ($pattern as $key => $value) {
                if (is_array($value)) {
                    return $checkAllKeys($value, $key);
                }

                if ($parent && !isset($data[$parent][$key])) {
                    throw new InvalidCampaignArgumentException(sprintf(
                        '%s field (%s) is missing. THe field is required.',
                        $key,
                        $parent
                    ));
                }

                if (!$parent && !isset($data[$key])) {
                    throw new InvalidCampaignArgumentException(sprintf(
                        '%s field is missing. THe field is required.',
                        $key
                    ));
                }
            }
        };

        $checkAllKeys($pattern);
    }
}
