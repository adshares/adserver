<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Repository;

use Adshares\Adserver\Exceptions\MissingInitialConfigurationException;
use Adshares\Common\Application\Dto\Media;
use Adshares\Common\Application\Dto\TaxonomyV2;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Application\Factory\MediaFactory;
use Adshares\Common\Application\Model\Selector;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Exception\InvalidArgumentException;
use ErrorException;
use Illuminate\Support\Facades\Log;

final class FileConfigurationRepository implements ConfigurationRepository
{
    private const FILTERING_CACHE_FILENAME = 'filtering.cache';
    private const TAXONOMY_CACHE_FILENAME = 'taxonomy.cache';

    private string $filteringFilePath;
    private string $taxonomyFilePath;

    public function __construct(string $cachePath)
    {
        $this->filteringFilePath = $cachePath . DIRECTORY_SEPARATOR . self::FILTERING_CACHE_FILENAME;
        $this->taxonomyFilePath = $cachePath . DIRECTORY_SEPARATOR . self::TAXONOMY_CACHE_FILENAME;
    }

    public function fetchFilteringOptions(): Selector
    {
        try {
            $data = file_get_contents($this->filteringFilePath);
        } catch (ErrorException $exception) {
            Log::error('No filtering data. Run command ops:filtering-options:update');
            throw new MissingInitialConfigurationException('No filtering data.');
        }
        return unserialize($data, [Selector::class]);
    }

    public function storeFilteringOptions(Selector $options): void
    {
        file_put_contents($this->filteringFilePath, serialize($options));
    }

    public function storeTaxonomyV2(TaxonomyV2 $taxonomy): void
    {
        file_put_contents($this->taxonomyFilePath, serialize($taxonomy));
    }

    public function fetchMedia(): Media
    {
        return MediaFactory::fromTaxonomy($this->getTaxonomyV2FromFile());
    }

    public function fetchTaxonomy(): TaxonomyV2
    {
        return $this->getTaxonomyV2FromFile();
    }

    public function fetchMedium(string $mediumName = 'web', ?string $vendor = null): Medium
    {
        foreach ($this->getTaxonomyV2FromFile()->getMedia() as $medium) {
            if ($medium->getName() === $mediumName && $medium->getVendor() === $vendor) {
                return $medium;
            }
        }
        throw new InvalidArgumentException('Unsupported medium');
    }

    private function getTaxonomyV2FromFile(): TaxonomyV2
    {
        try {
            $data = file_get_contents($this->taxonomyFilePath);
        } catch (ErrorException $exception) {
            Log::error('No taxonomy data. Run command ops:targeting-options:update');
            throw new MissingInitialConfigurationException('No taxonomy data.');
        }
        return unserialize($data, [TaxonomyV2::class]);
    }
}
