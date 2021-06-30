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

declare(strict_types=1);

namespace Adshares\Adserver\Repository;

use Adshares\Common\Application\Model\Selector;
use Adshares\Common\Application\Service\ConfigurationRepository;

use function file_get_contents;
use function file_put_contents;
use function serialize;
use function unserialize;

use const DIRECTORY_SEPARATOR;

final class FileConfigurationRepository implements ConfigurationRepository
{
    private const TARGETING_CACHE_FILENAME = 'targeting.cache';
    private const FILTERING_CACHE_FILENAME = 'filtering.cache';

    private $targetingFilePath;

    private $filteringFilePath;

    public function __construct(string $cachePath)
    {
        $this->targetingFilePath = $cachePath . DIRECTORY_SEPARATOR . self::TARGETING_CACHE_FILENAME;
        $this->filteringFilePath = $cachePath . DIRECTORY_SEPARATOR . self::FILTERING_CACHE_FILENAME;
    }

    public function storeTargetingOptions(Selector $options): void
    {
        file_put_contents($this->targetingFilePath, serialize($options));
    }

    public function fetchTargetingOptions(): Selector
    {
        $data = file_get_contents($this->targetingFilePath);

        if (!$data) {
            throw new \RuntimeException('No targeting data.');
        }

        return unserialize($data, [Selector::class]);
    }

    public function fetchFilteringOptions(): Selector
    {
        $data = file_get_contents($this->filteringFilePath);

        if (!$data) {
            throw new \RuntimeException('No filtering data.');
        }

        return unserialize($data, [Selector::class]);
    }

    public function storeFilteringOptions(Selector $options): void
    {
        file_put_contents($this->filteringFilePath, serialize($options));
    }
}
