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

declare(strict_types = 1);

namespace Adshares\Supply\Application\Dto;

use Adshares\Adserver\Client\Mapper\AbstractFilterMapper;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Zone;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Common\Exception\RuntimeException;
use function base64_decode;
use function bin2hex;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use stdClass;
use function str_replace;
use function substr;
use Symfony\Component\HttpFoundation\HeaderUtils;
use function array_shift;
use function json_encode;
use function sprintf;

final class ImpressionContext
{
    /** @var array */
    private $site;

    /** @var array */
    private $device;

    /** @var array */
    private $user;

    /** @var array|null */
    private $originalUser;

    public function __construct(array $site, array $device, array $user)
    {
        $this->site = $site;
        $this->device = $device;
        $this->user = $user;
    }

    public static function fromEventData($context, string $trackingId): self
    {
        $headersArray = get_object_vars($context->device->headers);

        $refererList = $headersArray['referer'] ?? [];
        $domain = $refererList[0] ?? '';

        $ip = $context->device->ip;

        $ua = $headersArray['user-agent'][0] ?? '';

        try {
            $trackingId = Utils::base64UrlEncodeWithChecksumFromBinUuidString(hex2bin($trackingId));
        } catch (RuntimeException $e) {
            Log::warning(sprintf('%s %s', $e->getMessage(), $trackingId));
            $trackingId = '';
        }

        return new self(
            ['domain' => $domain, 'page' => $domain],
            ['ip' => $ip, 'ua' => $ua],
            ['tid' => $trackingId]
        );
    }

    public function withUserDataReplacedBy(array $userData): self
    {
        $new = clone $this;

        if (empty($new->originalUser)) {
            $new->originalUser = $this->user;
        }

        $new->user = $userData;

        return $new;
    }

    public function toArray(): array
    {
        return [
            'site' => $this->site,
            'device' => $this->device,
            'user' => $this->user,
        ];
    }

    public function adUserRequestBody(): array
    {
        return [
            'url' => $this->site['page'] ?? '',
            'tags' => $this->site['keywords'] ?? [],
            'headers' => $this->flatHeaders(),
        ];
    }

    public function adSelectRequestParams(Collection $zones): array
    {
        $params = [];

        /* @var Zone */
        foreach ($zones as $requestId => $zone) {
            $trackingId = $this->hexUuidFromBase64UrlWithChecksum($this->trackingId());
            $userId = $this->userId();
            $params[] = [
                'keywords' => AbstractFilterMapper::generateNestedStructure($this->user['keywords']),
                'banner_size' => "{$zone->width}x{$zone->height}",
                'publisher_id' => Zone::fetchPublisherPublicIdByPublicId($zone->uuid),
                'site_id' => $zone->site->uuid,
                'zone_id' => $zone->uuid,
                'request_id' => $requestId,
                'user_id' => !empty($userId) ? $userId : $trackingId,
                'tracking_id' => $trackingId,
                'banner_filters' => $this->getBannerFilters($zone),
            ];
        }

        return $params;
    }

    private function getBannerFilters($zone): array
    {
        /** @var array $filtering */
        $filtering = $zone->site->filtering;

        $bannerFilters = [];
        $bannerFilters['require'] = $filtering['requires'] ?: new stdClass();
        $bannerFilters['exclude'] = $filtering['excludes'] ?: new stdClass();

        return $bannerFilters;
    }

    public function keywords(): array
    {
        return $this->site['keywords'] ?? [];
    }

    public function trackingId(): string
    {
        $trackingId = ($this->user['tid'] ?? false)
            ?: ($this->cookies()['tid'] ?? false)
                ?: ($this->originalUser['tid'] ?? '');

        return $trackingId;
    }

    public function userId(): string
    {
        $userId = $this->user['uid'] ?? '';

        if ($userId) {
            return Uuid::fromString($userId)->hex();
        }

        Log::warning(sprintf(
            '%s:%s Missing UID - {"user":%s,"cookies":%s,"oldUser":%s}',
            __METHOD__,
            __LINE__,
            json_encode($this->user) ?: 'null',
            json_encode($this->cookies()) ?: 'null',
            json_encode($this->originalUser) ?: 'null'
        ));

        return '';
    }

    private function flatHeaders(): array
    {
        $headers = array_map(
            function ($items) {
                return array_shift($items) ?? $items;
            },
            $this->device['headers'] ?? []
        );

        $headers['user-agent'] = ($headers['user-agent'] ?? $headers['User-Agent'] ?? false)
            ?: ($this->device['ua'] ?? '');

        return $headers;
    }

    private function cookies(): array
    {
        return HeaderUtils::combine(HeaderUtils::split($this->flatHeaders()['cookie'] ?? '', ';='));
    }

    private function hexUuidFromBase64UrlWithChecksum(string $trackingId): string
    {
        return bin2hex(substr($this->urlSafeBase64Decode($trackingId), 0, 16));
    }

    private function urlSafeBase64Decode(string $string): string
    {
        return base64_decode(
            str_replace(
                [
                    '_',
                    '-',
                ],
                [
                    '/',
                    '+',
                ],
                $string
            )
        );
    }
}
