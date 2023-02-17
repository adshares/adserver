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

declare(strict_types=1);

namespace Adshares\Adserver\Http\Requests\Campaign;

use Adshares\Adserver\Services\Common\MetaverseAddressValidator;
use Adshares\Adserver\Utilities\SiteValidator;
use Adshares\Adserver\ViewModel\MediumName;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Exception\InvalidArgumentException;

class CampaignTargetingProcessor
{
    private string $mediumName;
    private ?string $vendor;
    private TargetingProcessor $targetingProcessor;

    public function __construct(Medium $medium)
    {
        $this->mediumName = $medium->getName();
        $this->vendor = $medium->getVendor();
        $this->targetingProcessor = new TargetingProcessor($medium);
    }

    public function processTargetingRequire(array $targeting): array
    {
        $processed = $this->processTargeting($targeting, 'app.campaign_targeting_require');
        $this->validateDomainsIfPresent($processed);
        return $processed;
    }

    public function processTargetingExclude(array $targeting): array
    {
        $processed = $this->processTargeting($targeting, 'app.campaign_targeting_exclude');
        $this->validateDomainsIfPresent($processed);
        return $processed;
    }

    private function processTargeting(array $targeting, string $configKey): array
    {
        $serverTargeting = json_decode(config($configKey) ?? '', true);
        if (is_array($serverTargeting)) {
            $targeting = array_map([__CLASS__, 'normalize'], array_merge_recursive($targeting, $serverTargeting));
        }
        return $this->targetingProcessor->processTargeting($targeting);
    }

    private static function normalize($arr)
    {
        if (!is_array($arr) || empty($arr)) {
            return $arr;
        }
        if (array_keys($arr) !== range(0, count($arr) - 1)) {
            return $arr;
        }
        return array_unique($arr);
    }

    private function validateDomainsIfPresent(array $processed): void
    {
        $domains = $processed['site']['domain'] ?? [];
        $validator = MediumName::Metaverse->value === $this->mediumName
            ? MetaverseAddressValidator::fromVendor($this->vendor)
            : null;
        foreach ($domains as $domain) {
            if (!SiteValidator::isDomainValid($domain)) {
                throw new InvalidArgumentException(sprintf('Invalid domain %s', $domain));
            }
            $validator?->validateDomain($domain, true);
        }
    }
}
