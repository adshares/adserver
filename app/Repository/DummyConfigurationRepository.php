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

use Adshares\Common\Application\Model\Selector;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Application\Service\FilteringOptionsSource;
use Adshares\Common\Application\Service\TargetingOptionsSource;
use Exception;

final class DummyConfigurationRepository implements ConfigurationRepository
{
    /** @var TargetingOptionsSource */
    private $adUser;
    /** @var FilteringOptionsSource */
    private $adClassify;

    public function __construct(TargetingOptionsSource $userClient, FilteringOptionsSource $classifyClient)
    {
        $this->adUser = $userClient;
        $this->adClassify = $classifyClient;
    }

    public function storeTargetingOptions(Selector $options): void
    {
        throw new Exception('Method storeTargetingOptions() not implemented');
    }

    public function fetchTargetingOptions(): Selector
    {
        $taxonomy = $this->adUser->fetchTargetingOptions();

        return Selector::fromTaxonomy($taxonomy);
    }

    public function fetchFilteringOptions(): Selector
    {
        $taxonomy = $this->adClassify->fetchFilteringOptions();

        return Selector::fromTaxonomy($taxonomy);
    }

    public function storeFilteringOptions(Selector $options): void
    {
        throw new Exception('Method storeFilteringOptions() not implemented');
    }
}
