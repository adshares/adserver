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

namespace Adshares\Common\Application\Service;

use Adshares\Common\Application\Dto\Media;
use Adshares\Common\Application\Dto\TaxonomyV2;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Application\Model\Selector;

interface ConfigurationRepository
{
    public function storeFilteringOptions(Selector $options): void;

    public function storeTaxonomyV2(TaxonomyV2 $taxonomy): void;

    public function fetchFilteringOptions(): Selector;

    public function fetchTaxonomy(): TaxonomyV2;

    public function fetchMedia(): Media;

    public function fetchMedium(string $mediumName = 'web', ?string $vendor = null): Medium;
}
