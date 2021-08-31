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

namespace Adshares\Adserver\Services\Cdn;

use Adshares\Adserver\Models\Banner;
use GuzzleHttp\Client;

final class SkynetCdn extends CdnProvider
{
    private string $apiUrl;

    private string $apiKey;

    private string $cdnUrl;

    private static ?Client $client = null;

    public function __construct(string $apiUrl, string $apiKey, string $cdnUrl)
    {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
        $this->cdnUrl = $cdnUrl;
    }

    public function uploadBanner(Banner $banner): string
    {
        if (Banner::TEXT_TYPE_HTML === $banner->creative_type) {
            $mime = 'text/html';
        } elseif (Banner::TEXT_TYPE_IMAGE === $banner->creative_type) {
            $mime = 'image/png';
        } else {
            $mime = 'text/plain';
        }

        $client = $this->getClient();
        $response = $client->post(
            '/skynet/skyfile',
            [
                'multipart' => [
                    [
                        'name' => 'file',
                        'filename' => sprintf('x%s.doc', $banner->uuid),
                        'headers' => ['Content-Type' => $mime],
                        'contents' => $banner->creative_contents,
                    ],
                ],
            ]
        );

        $content = json_decode($response->getBody()->getContents());

        return sprintf('%s/%s/', $this->cdnUrl, $content->skylink);
    }

    private function getClient(): Client
    {
        if (null === self::$client) {
            self::$client = new Client(
                [
                    'headers' => [
                        'Cache-Control' => 'no-cache',
                        'Cookie' => sprintf('skynet-jwt=%s', $this->apiKey),
                    ],
                    'base_uri' => $this->apiUrl,
                    'timeout' => 10,
                ]
            );
        }

        return self::$client;
    }
}
