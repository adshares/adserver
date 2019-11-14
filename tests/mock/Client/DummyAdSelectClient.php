<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

declare(strict_types = 1);

namespace Adshares\Mock\Client;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\AdSelect;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Model\CampaignCollection;
use Adshares\Supply\Domain\ValueObject\Status;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use function hex2bin;

final class DummyAdSelectClient implements AdSelect
{
    public function findBanners(array $zones, ImpressionContext $context): FoundBanners
    {
        $banners = $this->getBestBanners($zones);

        return new FoundBanners($banners);
    }

    private function getBestBanners(array $zones): array
    {
        $bannerIds = [];
        foreach ($zones as $zoneInfo) {
            $zone = Zone::where('uuid', hex2bin($zoneInfo['zone']))->first();
            if (!$zone) {
                $bannerIds[] = '';
                continue;
            }

            try {
                $queryBuilder = $this->queryBuilder($zone);
                $bannerIds[] = bin2hex($queryBuilder->get(['network_banners.uuid'])->random()->uuid);
            } catch (InvalidArgumentException $e) {
                $bannerIds[] = '';
            }
        }

        $banners = [];
        foreach ($bannerIds as $bannerId) {
            $banner = $bannerId ? NetworkBanner::where('uuid', hex2bin($bannerId))->first() : NetworkBanner::first();

            if (empty($banner)) {
                $banners[] = null;
            } else {
                $campaign = NetworkCampaign::find($banner->network_campaign_id);
                $banners[] = [
                    'pay_from' => $campaign->source_address,
                    'pay_to' => AdsUtils::normalizeAddress(config('app.adshares_address')),
                    'serve_url' => $banner->serve_url,
                    'creative_sha1' => $banner->checksum,
                    'click_url' => route(
                        'log-network-click',
                        [
                            'id' => $banner->uuid,
                            'r' => Utils::urlSafeBase64Encode($banner->click_url),
                        ]
                    ),
                    'view_url' => route(
                        'log-network-view',
                        [
                            'id' => $banner->uuid,
                            'r' => Utils::urlSafeBase64Encode($banner->view_url),
                        ]
                    ),
                ];
            }
        }

        return $banners;
    }

    private function queryBuilder(Zone $zone): Builder
    {
        // TODO add targeting

        return DB::table('network_banners')->join(
            'network_campaigns',
            'network_banners.network_campaign_id',
            '=',
            'network_campaigns.id'
        )->where('network_campaigns.status', Status::STATUS_ACTIVE)->where(
            'network_banners.width',
            $zone->width
        )->where('network_banners.height', $zone->height);
    }

    public function exportInventory(CampaignCollection $campaigns): void
    {
    }

    public function deleteFromInventory(CampaignCollection $campaigns): void
    {
    }

    public function exportCases(Collection $cases): void
    {
    }

    public function exportCaseClicks(Collection $caseClicks): void
    {
    }

    public function exportCasePayments(Collection $casePayments): void
    {
    }

    public function getLastExportedCaseId(): int
    {
        return 0;
    }

    public function getLastExportedCaseClickId(): int
    {
        return 0;
    }

    public function getLastExportedCasePaymentId(): int
    {
        return 0;
    }
}
