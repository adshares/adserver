<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Client;

use Adshares\Classify\Domain\Model\Classification;
use Adshares\Supply\Application\Dto\Classification\Collection;
use Adshares\Supply\Application\Service\BannerClassifier;
use Adshares\Classify\Application\Service\ClassifierInterface;
use Adshares\Classify\Application\Exception\BannerNotVerifiedException;

class LocalPublisherBannerClassifier implements BannerClassifier
{
    private $classifier;

    public function __construct(ClassifierInterface $classifier)
    {
        $this->classifier = $classifier;
    }

    public function fetchBannersClassification(array $bannerIds): Collection
    {
        $collection = new Collection();
        foreach ($bannerIds as $bannerId) {
            try {
                $classificationCollection = $this->classifier->fetch($bannerId);

                /** @var Classification $classification */
                foreach ($classificationCollection as $classification) {
                    $collection->addClassification(
                        $bannerId,
                        $classification->keyword(),
                        $classification->signature()
                    );
                }
            } catch (BannerNotVerifiedException $exception) {
                $collection->addEmptyClassification($bannerId);
            }
        }

        return $collection;
    }
}
