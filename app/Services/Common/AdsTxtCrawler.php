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

namespace Adshares\Adserver\Services\Common;

use Adshares\Adserver\Facades\DB;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Spatie\Fork\Fork;
use Symfony\Component\HttpFoundation\Response;

class AdsTxtCrawler
{
    public function __construct(private readonly int $maxThreads = 4)
    {
    }

    public function checkSite(string $siteUrl, string $adServerDomain, string $publisherId): bool
    {
        return $this->checkIfSiteAdsTxtSupportsAdserver(
            $siteUrl,
            $adServerDomain,
            Uuid::fromString($publisherId)->toString(),
        );
    }

    public function checkSites(Collection $sites): array
    {
        $adServerDomain = config('app.ads_txt_domain');
        $tasks = [];
        foreach ($sites as $site) {
            $siteId = $site->id;
            $siteUrl = $site->url;
            $publisherId = Uuid::fromString($site->user->uuid)->toString();
            $tasks[] = fn() => [
                'id' => $siteId,
                'result' => $this->checkIfSiteAdsTxtSupportsAdserver($siteUrl, $adServerDomain, $publisherId)
            ];
        }

        $taskResults = Fork::new()
            ->concurrent($this->maxThreads)
            ->after(parent: DB::connection()->reconnect())
            ->run(...$tasks);

        $result = [];
        foreach ($taskResults as $taskResult) {
            $result[$taskResult['id']] = $taskResult['result'];
        }
        return $result;
    }

    private function checkIfSiteAdsTxtSupportsAdserver(
        string $siteUrl,
        string $adServerDomain,
        string $publisherId,
    ): bool {
        $parsedUrl = parse_url($siteUrl);
        $scheme = $parsedUrl['scheme'];
        $host = $parsedUrl['host'];

        $hostParts = explode('.', $host);
        $maxChecks = count($hostParts) - 1;
        for ($i = 0; $i < $maxChecks; $i++) {
            $host = implode('.', array_slice($hostParts, $i));
            $url = sprintf('%s://%s/ads.txt', $scheme, $host);
            try {
                $response = Http::timeout(1)->get($url);
                if (
                    Response::HTTP_OK === $response->status()
                    && $this->isSiteInAdsTxt($response->body(), $adServerDomain, $publisherId)
                ) {
                    return true;
                }
            } catch (HttpClientException $exception) {
                Log::info(sprintf('Checking ads.txt of %s failed: %s', $siteUrl, $exception->getMessage()));
            }
        }
        return false;
    }

    private function isSiteInAdsTxt(string $adsTxtContent, string $expectedDomain, string $expectedPublisherId): bool
    {
        $lines = explode(PHP_EOL, $adsTxtContent);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $fields = explode(',', $line);
            if (count($fields) < 3) {
                continue;
            }
            $domain = trim($fields[0]);
            $publisherId = trim($fields[1]);
            $relationship = trim($fields[2]);
            if ($expectedDomain === $domain && $expectedPublisherId === $publisherId && 'DIRECT' === $relationship) {
                return true;
            }
        }
        return false;
    }
}
