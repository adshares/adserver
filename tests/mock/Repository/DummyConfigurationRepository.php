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

namespace Adshares\Mock\Repository;

use Adshares\Common\Application\Factory\TaxonomyFactory;
use Adshares\Common\Application\Model\Selector;
use Adshares\Common\Application\Service\ConfigurationRepository;

use function GuzzleHttp\json_decode;

class DummyConfigurationRepository implements ConfigurationRepository
{
    public function storeTargetingOptions(Selector $options): void
    {
    }

    public function storeFilteringOptions(Selector $options): void
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
        $taxonomy = TaxonomyFactory::fromArray($decodedTaxonomy);

        return Selector::fromTaxonomy($taxonomy);
    }
}
