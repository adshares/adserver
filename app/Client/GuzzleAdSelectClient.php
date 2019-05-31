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

namespace Adshares\Adserver\Client;

use Adshares\Adserver\Client\Mapper\AdSelect\CampaignMapper;
use Adshares\Adserver\Client\Mapper\AdSelect\EventMapper;
use Adshares\Adserver\Client\Mapper\AdSelect\EventPaymentMapper;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\RuntimeException as DomainRuntimeException;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\AdSelect;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Model\CampaignCollection;
use InvalidArgumentException;
use Log;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Collection;
use function array_map;
use function GuzzleHttp\json_decode;
use function iterator_to_array;
use function json_encode;
use function config;
use function route;
use function sprintf;
use function strtolower;
use Symfony\Component\HttpFoundation\Response;

class GuzzleAdSelectClient implements AdSelect
{
    /** @var Client */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function exportInventory(Campaign $campaign): void
    {
        $mapped = CampaignMapper::map($campaign);

        try {
            $this->client->post('/api/v1/campaigns', [
                RequestOptions::JSON => ['campaigns' => $mapped],
            ]);
        } catch (RequestException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf(
                    '[ADSELECT] Export inventory to %s failed (%s).',
                    $this->client->getConfig()['base_uri'],
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }
    }

    public function deleteFromInventory(CampaignCollection $campaigns): void
    {
        $mapped = [];

        /** @var Campaign $campaign */
        foreach ($campaigns as $campaign) {
            $mapped[] = $campaign->getId();
        }

        try {
            $this->client->delete('/api/v1/campaigns', [
                RequestOptions::JSON => ['campaigns' => $mapped],
            ]);
        } catch (RequestException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf(
                    '[ADSELECT] Delete campaigns (%s) from %s failed (%s).',
                    json_encode($mapped),
                    $this->client->getConfig()['base_uri'],
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }
    }

    public function findBanners(array $zones, ImpressionContext $context): FoundBanners
    {
        $zoneIds = array_map(
            static function (array $zone) {
                return strtolower((string)$zone['zone']);
            },
            $zones
        );

        $zoneCollection = Zone::findByPublicIds($zoneIds);

        if ($zoneCollection->count() < count($zoneIds)) {
            $zoneCollection = self::attachDuplicatedZones($zoneCollection, $zoneIds);
        }

        $existingZones = $zoneCollection->reject(static function ($zone) {
            return $zone === null;
        });

        try {
            $result = $this->client->post('/api/v1/find', [
                RequestOptions::JSON =>  $context->adSelectRequestParams($existingZones),
            ]);
        } catch (RequestException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf(
                    '[ADSELECT] Find banners (%s) from %s failed (%s).',
                    json_encode($existingZones),
                    $this->client->getConfig()['base_uri'],
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }

        $body = (string)$result->getBody();
        try {
            $items = json_decode($body, true);
        } catch (InvalidArgumentException $exception) {
            throw new DomainRuntimeException(sprintf('[ADSELECT] Find Banners. Invalid json data (%s).', $body));
        }
        Log::debug(sprintf(
            '%s:%s %s',
            __METHOD__,
            __LINE__,
            $body
        ));

        $bannerIds = $this->fixBannerOrdering($existingZones, $items, $zoneIds);
        $banners = iterator_to_array($this->fetchInOrderOfAppearance($bannerIds));

        return new FoundBanners($banners);
    }

    public function exportEvents(array $events): void
    {
        $mapped = [];

        foreach ($events as $event) {
            $mapped[] = EventMapper::map($event);
        }

        try {
            $this->client->post('/api/v1/events/unpaid', [
                RequestOptions::JSON => ['events' => $mapped],
            ]);
        } catch (RequestException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf(
                    '[ADSELECT] Export unpaid events from %s failed (%s).',
                    $this->client->getConfig()['base_uri'],
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }
    }

    public function exportEventsPayments(array $events): void
    {
        $mapped = [];

        foreach ($events as $event) {
            $mapped[] = EventPaymentMapper::map($event);
        }

        try {
            $this->client->post('/api/v1/events/paid', [
                RequestOptions::JSON => ['events' => $mapped],
            ]);
        } catch (RequestException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf(
                    '[ADSELECT] Export paid events from %s failed (%s).',
                    $this->client->getConfig()['base_uri'],
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }
    }

    private static function attachDuplicatedZones(Collection $uniqueZones, array $zoneIds): Collection
    {
        $zones = [];
        foreach ($zoneIds as $zonePublicIdPassedFromPublisher) {
            $zones[] = $uniqueZones->filter(
                static function (Zone $zone) use ($zonePublicIdPassedFromPublisher) {
                    return $zone->uuid === $zonePublicIdPassedFromPublisher;
                }
            )->first();
        }

        return new Collection($zones);
    }

    private function fixBannerOrdering(Collection $zones, array $bannerMap, array $zoneIds): array
    {
        $bannerIds = [];

        foreach ($zones as $requestId => $zone) {
            $banner = $bannerMap[$requestId] ?? null;

            if ($banner === null) {
                Log::warning(sprintf('Banner for zone 0x%s (%s) not found', $zone->uuid, $zone->id));
            }

            $bannerIds[$zone->uuid][] = $banner[0] ?? null;
        }

        $orderedBannerIds = [];

        foreach ($zoneIds as $zoneId) {
            $orderedBannerIds[$zoneId] = $bannerIds[$zoneId] ?? [null];
        }

        return $orderedBannerIds;
    }

    private function fetchInOrderOfAppearance(array $params): Generator
    {
        foreach ($params as $zoneId => $bannerIds) {
            foreach ($bannerIds as $item) {
                $bannerId = $item['banner_id'] ?? null;
                $banner = $bannerId ? NetworkBanner::findByUuid((string)$bannerId) : null;

                if (null === $banner) {
                    if ($bannerId) {
                        Log::warning(sprintf('Banner %s not found.', $bannerId));
                    }

                    yield null;
                } else {
                    $campaign = $banner->campaign;
                    yield [
                        'id' => $bannerId,
                        'zone_id' => $zoneId,
                        'pay_from' => $campaign->source_address,
                        'pay_to' => AdsUtils::normalizeAddress(config('app.adshares_address')),
                        'serve_url' => $banner->serve_url,
                        'creative_sha1' => $banner->checksum,
                        'click_url' => SecureUrl::change(
                            route(
                                'log-network-click',
                                [
                                    'id' => $banner->uuid,
                                    'r' => Utils::urlSafeBase64Encode($banner->click_url),
                                ]
                            )
                        ),
                        'view_url' => SecureUrl::change(
                            route(
                                'log-network-view',
                                [
                                    'id' => $banner->uuid,
                                    'r' => Utils::urlSafeBase64Encode($banner->view_url),
                                ]
                            )
                        ),
                    ];
                }
            }
        }
    }

    public function getLastPaidEventId(): int
    {
        return $this->getLastEventId('paid');
    }

    public function getLastUnpaidEventId(): int
    {
        return $this->getLastEventId('unpaid');
    }

    private function getLastEventId(string $type): int
    {
        try {
            $uri = sprintf('/api/v1/events/%s/last', $type);
            $response = $this->client->get($uri);
        } catch (RequestException $exception) {
            if ($exception->getCode() === Response::HTTP_NOT_FOUND) {
                return 0;
            }

            throw new UnexpectedClientResponseException(
                sprintf(
                    '[ADSELECT] Fetch last %s event id from %s failed (%s).',
                    $type,
                    $this->client->getConfig()['base_uri'],
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }

        $body = (string)$response->getBody();
        try {
            $item = json_decode($body, true);
        } catch (InvalidArgumentException $exception) {
            throw new DomainRuntimeException(sprintf(
                '[ADSELECT] Fetch last %s events. Invalid json data (%s).',
                $type,
                $body
            ));
        }

        if (!isset($item['id']) || !array_key_exists('payment_id', $item)) {
            throw new UnexpectedClientResponseException(sprintf(
                '[ADSELECT] Could not fetch last %s event from adselect (%s). Event id is required, given: %s.',
                $type,
                $this->client->getConfig()['base_uri'],
                $body
            ));
        }

        if ($type === 'paid' ) {
            return (int)$item['payment_id'];
        }

        return (int)$item['id'];
    }
}
