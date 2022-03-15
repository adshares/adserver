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

namespace Adshares\Mock\Repository;

use Adshares\Common\Application\Dto\Media;
use Adshares\Common\Application\Dto\TaxonomyV4;
use Adshares\Common\Application\Dto\TaxonomyV4\Medium;
use Adshares\Common\Application\Factory\MediaFactory;
use Adshares\Common\Application\Factory\TaxonomyV3Factory;
use Adshares\Common\Application\Factory\TaxonomyV4Factory;
use Adshares\Common\Application\Model\Selector;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Exception\InvalidArgumentException;

use function GuzzleHttp\json_decode;

class DummyConfigurationRepository implements ConfigurationRepository
{
    public function storeTargetingOptions(Selector $options): void
    {
    }

    public function storeFilteringOptions(Selector $options): void
    {
    }

    public function storeTaxonomyV4(TaxonomyV4 $taxonomy): void
    {
    }

    public function fetchTargetingOptions(): Selector
    {
        return $this->getTaxonomyFromFile('tests/mock/targeting_schema_v3.json');
    }

    public function fetchFilteringOptions(): Selector
    {
        return $this->getTaxonomyFromFile('tests/mock/filtering_schema.json');
    }

    private function getTaxonomyFromFile(string $fileName): Selector
    {
        $path = base_path($fileName);
        $var = file_get_contents($path);
        $decodedTaxonomy = json_decode($var, true);
        $taxonomy = TaxonomyV3Factory::fromArray($decodedTaxonomy);

        return Selector::fromTaxonomy($taxonomy);
    }

    private static function getTaxonomyV4FromFile(): TaxonomyV4
    {
        $path = base_path('tests/mock/targeting_schema_v4.json');
        $json = file_get_contents($path);
        return TaxonomyV4Factory::fromJson($json);
    }

    public function fetchTaxonomy(): TaxonomyV4
    {
        return self::getTaxonomyV4FromFile();
    }

    public function fetchMedia(): Media
    {
        return MediaFactory::fromTaxonomy(self::getTaxonomyV4FromFile());
    }

    public function fetchMedium(string $mediumName = 'web', ?string $vendor = null): Medium
    {
        foreach (self::getTaxonomyV4FromFile()->getMedia() as $medium) {
            if ($medium->getName() === $mediumName) {
                return $medium;
            }
        }
        throw new InvalidArgumentException('Unsupported medium');
    }
}
