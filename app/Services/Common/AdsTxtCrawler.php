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
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Utilities\DomainReader;
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

    public function checkSite(Site $site): bool
    {
        $siteUrl = $site->url;
        $adServerDomain = $this->getAdServerDomain();
        $publisherId = Uuid::fromString($site->user->uuid)->toString();
        return $this->checkIfSiteAdsTxtSupportsAdserver($siteUrl, $adServerDomain, $publisherId);
    }

    public function checkSites(Collection $sites): array
    {
        $adServerDomain = $this->getAdServerDomain();
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

    private function getAdServerDomain(): string
    {
        $domain = DomainReader::domain(config('app.url'));
        if (str_starts_with($domain, 'app.')) {
            $domain = substr($domain, 4);
        }
        return $domain;
    }

    private function checkIfSiteAdsTxtSupportsAdserver(
        string $siteUrl,
        string $adServerDomain,
        string $publisherId,
    ): bool {
        $url = sprintf('%s/ads.txt', $siteUrl);
        try {
            $response = Http::get($url);
            if (Response::HTTP_OK === $response->status()) {
                return $this->isSiteInAdsTxt($response->body(), $adServerDomain, $publisherId);
            }
        } catch (HttpClientException $exception) {
            Log::info(sprintf('Checking ads.txt of %s failed: %s', $siteUrl, $exception->getMessage()));
        }
        return false;
    }

    private function isSiteInAdsTxt(string $adsTxt, string $expectedDomain, string $expectedPublisherId): bool
    {
        $lines = explode(PHP_EOL, $adsTxt);
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
