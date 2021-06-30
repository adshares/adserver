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

namespace Adshares\Adserver\Client;

use Adshares\Adserver\Repository\Common\ClassifierExternalRepository;
use Adshares\Adserver\Repository\Common\Dto\ClassifierExternal;
use Adshares\Common\Application\Dto\Taxonomy;
use Adshares\Common\Application\Factory\TaxonomyFactory;
use Adshares\Common\Application\Service\AdClassify;

final class MultipleExternalClassifierAdClassifyClient implements AdClassify
{
    private const NAMESPACE_SEPARATOR = ':';

    /** @var ClassifierExternalClient */
    private $client;

    /** @var ClassifierExternalRepository */
    private $classifierRepository;

    public function __construct(
        ClassifierExternalClient $client,
        ClassifierExternalRepository $classifierRepository
    ) {
        $this->client = $client;
        $this->classifierRepository = $classifierRepository;
    }

    public function fetchFilteringOptions(): Taxonomy
    {
        $classifiers = $this->classifierRepository->fetchClassifiers();

        $data = [];
        /** @var ClassifierExternal $classifier */
        foreach ($classifiers as $classifier) {
            $namespace = $classifier->getName();
            $taxonomyRaw = $this->client->fetchTaxonomy($classifier)->getRawData();

            if (!isset($taxonomyRaw['data'])) {
                continue;
            }

            foreach ($taxonomyRaw['data'] as $option) {
                $option['key'] = $namespace . self::NAMESPACE_SEPARATOR . $option['key'];
                $data[] = $option;
            }
        }

        return TaxonomyFactory::fromArray(['data' => $data]);
    }
}
