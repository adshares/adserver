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

use Adshares\Common\Application\Dto\Media;
use Adshares\Common\Application\Dto\TaxonomyV4;
use Adshares\Common\Application\Dto\TaxonomyV4\Medium;
use Adshares\Common\Application\Factory\MediaFactory;
use Adshares\Common\Application\Model\Selector;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Common\Exception\RuntimeException;

final class FileConfigurationRepository implements ConfigurationRepository
{
    private const TARGETING_CACHE_FILENAME = 'targeting.cache';
    private const FILTERING_CACHE_FILENAME = 'filtering.cache';
    private const TAXONOMY_CACHE_FILENAME = 'taxonomy.cache';

    private string $targetingFilePath;
    private string $filteringFilePath;
    private string $taxonomyFilePath;

    public function __construct(string $cachePath)
    {
        $this->targetingFilePath = $cachePath . DIRECTORY_SEPARATOR . self::TARGETING_CACHE_FILENAME;
        $this->filteringFilePath = $cachePath . DIRECTORY_SEPARATOR . self::FILTERING_CACHE_FILENAME;
        $this->taxonomyFilePath = $cachePath . DIRECTORY_SEPARATOR . self::TAXONOMY_CACHE_FILENAME;
    }

    public function storeTargetingOptions(Selector $options): void
    {
        file_put_contents($this->targetingFilePath, serialize($options));
    }

    public function fetchTargetingOptions(): Selector
    {
        $data = file_get_contents($this->targetingFilePath);

        if (!$data) {
            throw new RuntimeException('No targeting data.');
        }

        return unserialize($data, [Selector::class]);
    }

    public function fetchFilteringOptions(): Selector
    {
        $data = file_get_contents($this->filteringFilePath);

        if (!$data) {
            throw new RuntimeException('No filtering data.');
        }

        return unserialize($data, [Selector::class]);
    }

    public function storeFilteringOptions(Selector $options): void
    {
        file_put_contents($this->filteringFilePath, serialize($options));
    }

    public function storeTaxonomyV4(TaxonomyV4 $taxonomy): void
    {
        file_put_contents($this->taxonomyFilePath, serialize($taxonomy));
    }

    public function fetchMedia(): Media
    {
        return MediaFactory::fromTaxonomy($this->getTaxonomyV4FromFile());
    }

    public function fetchTaxonomy(): TaxonomyV4
    {
        return $this->getTaxonomyV4FromFile();
    }

    public function fetchMedium(string $mediumName = 'web'): Medium
    {
        foreach ($this->getTaxonomyV4FromFile()->getMedia() as $medium) {
            if ($medium->getName() === $mediumName) {
                return $medium;
            }
        }
        throw new InvalidArgumentException('Unsupported medium');
    }

    private function getTaxonomyV4FromFile(): TaxonomyV4
    {
        $data = file_get_contents($this->taxonomyFilePath);

        if (!$data) {
            throw new RuntimeException('No taxonomy data.');
        }

        return unserialize($data, [TaxonomyV4::class]);
    }
}
