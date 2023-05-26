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

namespace Adshares\Adserver\Services\Supply;

use Adshares\Adserver\Models\ServeDomain;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\SupplyBannerPlaceholder;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Adshares\Supply\Application\Dto\FoundBanners;

class BannerPlaceholderProvider
{
    public function addBannerPlaceholders(array $bannerPlaceholders): void
    {
        foreach ($bannerPlaceholders as $bannerPlaceholder) {
            SupplyBannerPlaceholder::register(
                $bannerPlaceholder['medium'],
                $bannerPlaceholder['vendor'],
                $bannerPlaceholder['size'],
                $bannerPlaceholder['type'],
                $bannerPlaceholder['mime'],
                $bannerPlaceholder['content'],
            );
        }
    }

    public function deleteBannerPlaceholders(array $bannerPlaceholderIds): void
    {
        SupplyBannerPlaceholder::deleteByPublicIds($bannerPlaceholderIds);
    }

    public function findBannerPlaceholders(array $zones, string $impressionId): FoundBanners
    {
        /** @var LicenseReader $licenseReader */
        $licenseReader = resolve(LicenseReader::class);
        $infoBox = $licenseReader->getInfoBox();

        $adserverAddress = AdsUtils::normalizeAddress(config('app.adshares_address'));

        $zoneInputByUuid = [];
        $zoneIds = [];
        foreach ($zones as $zone) {
            $zoneId = $zone['placementId'] ?? (string)$zone['zone'];// Key 'zone' is for legacy search
            $zoneInputByUuid[$zoneId] = $zone;
            $zoneIds[] = strtolower($zoneId);
        }

        $zoneMap = [];
        $sitesMap = [];

        $zoneList = Zone::findByPublicIds($zoneIds);
        $zoneListCount = count($zoneList);
        /** @var Zone $zone */
        for ($i = 0; $i < $zoneListCount; $i++) {
            $zone = $zoneList[$i];
            $siteId = $zone->site_id;

            if (!array_key_exists($siteId, $sitesMap)) {
                $site = $zone->site;

                $isActive = null !== $site && $site->status === Site::STATUS_ACTIVE && null !== $site->user;

                if ($isActive) {
                    $sitesMap[$siteId] = [
                        'active' => true,
                        'medium' => $site->medium,
                        'vendor' => $site->vendor,
                    ];
                } else {
                    $sitesMap[$siteId] = [
                        'active' => false,
                    ];
                }
            }

            if ($sitesMap[$siteId]['active']) {
                $zoneMap[$zone->uuid] = $zone;
            }
        }

        $banners = [];
        foreach ($zoneIds as $i => $id) {
            $requestId = $zoneInputByUuid[$id]['id'] ?? $i;
            $zone = $zoneMap[$id] ?? null;

            if (null === $zone) {
                $banners[] = null;
                continue;
            }

            $options = $zoneInputByUuid[$zone->uuid]['options'] ?? [];
            $types = isset($options['banner_type']) ? (array)$options['banner_type'] : null;
            $mimes = isset($options['banner_mime']) ? (array)$options['banner_mime'] : null;
            $placeholder = SupplyBannerPlaceholder::fetch(
                $sitesMap[$zone->site_id]['medium'],
                $sitesMap[$zone->site_id]['vendor'],
                $zone->scopes,
                $types,
                $mimes,
            );

            if (null === $placeholder) {
                $banners[] = null;
                continue;
            }

            $banners[] = [
                'id' => $placeholder->uuid,
                'publisher_id' => '00000000000000000000000000000000',
                'zone_id' => $zone->uuid,
                'pay_from' => $adserverAddress,
                'pay_to' => $adserverAddress,
                'type' => $placeholder->type,
                'size' => $placeholder->size,
                'serve_url' => ServeDomain::changeUrlHost(
                    (new SecureUrl(
                        route(
                            'placeholder-serve',
                            [
                                'id' => $placeholder->uuid,
                            ]
                        )
                    ))->toString()
                ),
                'creative_sha1' => $placeholder->checksum,
                'click_url' => ServeDomain::changeUrlHost(
                    (new SecureUrl(
                        route(
                            'log-placeholder-click',
                            [
                                'id' => $placeholder->uuid,
                                'iid' => $impressionId,
                                'zid' => $zone->uuid,
                            ]
                        )
                    ))->toString()
                ),
                'view_url' => ServeDomain::changeUrlHost(
                    (new SecureUrl(
                        route(
                            'log-placeholder-view',
                            [
                                'id' => $placeholder->uuid,
                                'iid' => $impressionId,
                                'zid' => $zone->uuid,
                            ]
                        )
                    ))->toString()
                ),
                'info_box' => $infoBox,
                'rpm' => 0,
                'request_id' => (string)$requestId,
            ];
        }

        return new FoundBanners($banners);
    }
}
