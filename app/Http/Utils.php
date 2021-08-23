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

namespace Adshares\Adserver\Http;

use Adshares\Adserver\Utilities\UuidStringGenerator;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Dto\UserContext;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

use function bin2hex;
use function config;
use function is_string;
use function sha1;
use function sprintf;
use function strlen;
use function substr;

use const true;

class Utils
{
    private const VALUE_GLUE = "\t";

    private const PROP_GLUE = "\r";

    private const ZONE_GLUE = "\n";

    public const ENV_DEV = 'local';

    public const ENV_PROD = 'production';

    public static function getPartialImpressionContext(
        Request $request,
        $contextStr = null,
        $tid = null
    ): ImpressionContext {
        $context = self::getImpressionContextArray($request, $contextStr);

        return new ImpressionContext($context['site'], $context['device'], $tid ? ['tid' => $tid] : []);
    }

    public static function getImpressionContextArray(Request $request, $contextStr = null): array
    {
        $contextStr = $contextStr ?: $request->query->get('ctx');
        if ($contextStr) {
            if (is_string($contextStr)) {
                $context = self::decodeZones($contextStr);
            } else {
                $context = ['page' => $contextStr];
            }
        } else {
            $context = null;
        }

        if ($context['page']['frame']) {
            $context['page']['frame_url'] = $context['page']['url'];
            $context['page']['url'] = $context['page']['ref'];
            $context['page']['ref'] = '';
        }

        return [
            'site'   => self::getSiteContext($request, $context),
            'device' => [
                'ip'      => $request->ip(),
                'headers' => [
                    'referer' => [$request->header('referer')],
                    'user-agent' => [$request->header('user-agent')],
                ],
            ],
        ];
    }

    public static function decodeZones($zonesStr): array
    {
        $zonesStr = self::urlSafeBase64Decode($zonesStr);

        $zones = explode(self::ZONE_GLUE, $zonesStr);
        $fields = explode(self::VALUE_GLUE, array_shift($zones));

        $data = [];

        foreach ($zones as $zoneStr) {
            $zone = [];
            $propStrs = explode(self::PROP_GLUE, $zoneStr);
            foreach ($propStrs as $propStr) {
                $prop = explode(self::VALUE_GLUE, $propStr);
                if (!isset($prop[1]) || !isset($fields[$prop[0]])) {
                    throw new RuntimeException('Unprocessable property during decoding zones');
                }
                $zone[$fields[$prop[0]]] = is_numeric($prop[1]) ? floatval($prop[1]) : $prop[1];
            }
            $opts = [];
            if (isset($zone['options'])) {
                foreach (explode(',', $zone['options']) as $entry) {
                    list($key, $value) = explode('=', trim($entry), 2);
                    $opts[trim($key)] = trim($value);
                }
            }
            $zone['options'] = $opts;
            $data[] = $zone;
        }

        $result = [
            'page' => array_shift($data),
        ];
        if ($data) {
            $result['zones'] = $data;
        }

        return $result;
    }

    public static function urlSafeBase64Decode(string $string): string
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

    public static function getSiteContext(Request $request, $context): array
    {
        $site = [];

        if (empty($context) || !isset($context['page'])) {
            return $site;
        }

        $page = $context['page'];

        if (!$page['url']) {
            $page['url'] = $request->headers->get('Referer');
        }
        $url = parse_url($page['url']);
        $site['domain'] = $url['host'] ?? null;
        $site['inframe'] = isset($page['frame']) ? ($page['frame'] ? 'yes' : 'no') : null;
        $site['page'] = $page['url'];
        if (isset($page['keywords']) && is_string($page['keywords'])) {
            $site['keywords'] = explode(',', $page['keywords']);
            foreach ($site['keywords'] as &$word) {
                $word = strtolower(trim($word));
            }
        }
        $site['referrer'] = $page['ref'] ?? '';
        $site['popup'] = $page['pop'] ?? '';

        return $site;
    }

    public static function addUrlParameter($url, $name, $value): string
    {
        $param = $name . '=' . urlencode($value);
        $qPos = strpos($url, '?');
        if (false === $qPos) {
            return $url . '?' . $param;
        } elseif ($qPos === strlen($url) - 1) {
            return $url . $param;
        } else {
            return $url . '&' . $param;
        }
    }

    public static function attachOrProlongTrackingCookie(
        Request $request,
        Response $response,
        $contentSha1,
        DateTime $contentModified,
        ?string $impressionId = null
    ): string {
        $tid = self::getOrCreateTrackingId($request, $impressionId);

        $response->headers->setCookie(
            new Cookie(
                'tid',
                $tid,
                new DateTime('+ 1 month'),
                '/',
                $request->getHost(),
                config('app.banner_force_https') || $request->isSecure(),
                true,
                false,
                'none'
            )
        );

        $response->headers->set('P3P', 'CP="CAO OUR"'); // Platform for Privacy Preferences

        $response->setCache(
            [
                'etag'          => self::generateEtag($tid, $contentSha1),
                'last_modified' => $contentModified,
                'max_age'       => 0,
                'private'       => true,
            ]
        );

        $response->headers->addCacheControlDirective('no-transform');

        return $tid;
    }

    public static function urlSafeBase64Encode($string): string
    {
        return str_replace(
            [
                '/',
                '+',
                '=',
            ],
            [
                '_',
                '-',
                '',
            ],
            base64_encode($string)
        );
    }

    private static function generateEtag($tid, $contentSha1): string
    {
        $sha1 = pack('H*', $contentSha1);

        return self::urlSafeBase64Encode(substr($sha1, 0, 6) . strrev(self::urlSafeBase64Decode($tid)));
    }

    public static function createCaseIdContainingEventType(string $baseCaseId, string $eventType): string
    {
        $caseId = substr($baseCaseId, 0, -2);

        if ($eventType === 'view') {
            return $caseId . '02';
        }

        if ($eventType === 'click') {
            return $caseId . '03';
        }

        if ($eventType === 'shadow-click') {
            return $caseId . '04';
        }

        throw new RuntimeException(sprintf('Invalid event type %s for case id %s', $eventType, $baseCaseId));
    }

    public static function getZoneIdFromContext(string $zoneStr): string
    {
        $context = self::decodeZones($zoneStr);

        if (!isset($context['page']['zone'])) {
            throw new RuntimeException('Missing zone id');
        }

        $zoneId = $context['page']['zone'];

        if (!Utils::isUuidValid($zoneId)) {
            throw new RuntimeException(sprintf('Invalid zone id format (%s)', $zoneId));
        }

        return $zoneId;
    }

    public static function mergeImpressionContextAndUserContext(
        ImpressionContext $impressionContext,
        UserContext $userContext
    ): ImpressionContext {
        return $impressionContext->withUserDataReplacedBy($userContext->toAdSelectPartialArray());
    }

    private static function binUserId(?string $nonce = null): string
    {
        $input = [];

        if ($nonce !== null) {
            $input[] = $nonce;
            $input[] = $_SERVER['REMOTE_ADDR'] ?? '';
        } else {
            $input[] = microtime();
            $input[] = $_SERVER['REMOTE_ADDR'] ?? mt_rand();
            $input[] = $_SERVER['REMOTE_PORT'] ?? mt_rand();
            $input[] = $_SERVER['REQUEST_TIME_FLOAT'] ?? mt_rand();
            $input[] = is_callable('random_bytes') ? random_bytes(22) : openssl_random_pseudo_bytes(22);
        }

        return substr(sha1(implode(':', $input), true), 0, 16);
    }

    public static function hexUserId(): string
    {
        return bin2hex(self::binUserId());
    }

    private static function getOrCreateTrackingId(Request $request, ?string $impressionId): string
    {
        $tid = $request->cookies->get('tid') ?? '';

        if (self::validTrackingId($tid)) {
            return $tid;
        }

        return self::base64UrlEncodeWithChecksumFromBinUuidString(self::binUserId($impressionId));
    }

    private static function validTrackingId(string $tid): bool
    {
        if (!$tid) {
            return false;
        }

        $binTid = self::urlSafeBase64Decode($tid);

        return substr($binTid, 16, 6) === self::checksum(substr($binTid, 0, 16));
    }

    public static function base64UrlEncodeWithChecksumFromBinUuidString(string $id): string
    {
        if (strlen($id) !== 16) {
            throw new RuntimeException('UserId should be a 16-byte binary format string.');
        }

        return self::urlSafeBase64Encode($id . self::checksum($id));
    }

    private static function checksum(string $id)
    {
        if (strlen($id) !== 16) {
            throw new RuntimeException('Id should be a 16-byte binary format string.');
        }

        return substr(sha1($id . config('app.adserver_secret'), true), 0, 6);
    }

    public static function hexUuidFromBase64UrlWithChecksum(string $trackingId): string
    {
        return bin2hex(substr(self::urlSafeBase64Decode($trackingId), 0, 16));
    }

    public static function base64Encoded16BytesSecret(): string
    {
        try {
            $bytes = random_bytes(16);
        } catch (Exception $exception) {
            $bytes = hex2bin(UuidStringGenerator::v4());
        }

        return Utils::urlSafeBase64Encode($bytes);
    }

    public static function isUuidValid($uuid): bool
    {
        return is_string($uuid) && 1 === preg_match('/^[0-9a-f]{32}$/i', $uuid);
    }
}
