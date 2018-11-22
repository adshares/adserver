<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types = 1);

namespace Adshares\Adserver\Repository;

use Adshares\Common\Domain\Service\AdUserClient;
use Adshares\Common\Domain\Service\OptionsRepository;
use Adshares\Common\Domain\ValueObject\TargetingOptions;
use Exception;

final class DummyOptionsRepository implements OptionsRepository
{
    /** @var AdUserClient */
    private $adUserClient;

    public function __construct(AdUserClient $adUserClient)
    {
        $this->adUserClient = $adUserClient;
    }

    public function storeTargetingOptions(TargetingOptions $options): void
    {
        throw new Exception("Method storeTargetingOptions() not implemented");
    }

    public function fetchTargetingOptions(): TargetingOptions
    {
        $taxonomy = $this->adUserClient->fetchTaxonomy();

        return $taxonomy->toTargetingOptions();
    }
}
