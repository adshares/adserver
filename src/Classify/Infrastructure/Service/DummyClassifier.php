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

namespace Adshares\Classify\Infrastructure\Service;

use Adshares\Classify\Application\Service\ClassifierInterface;
use Adshares\Classify\Application\Exception\BannerNotVerifiedException;
use Adshares\Classify\Domain\Model\Classification;
use Adshares\Classify\Domain\Model\ClassificationCollection;

use function array_key_exists;

class DummyClassifier implements ClassifierInterface
{
    /** @var string */
    private $keyword;

    private $banners = [
        '1' => [
            'bannerId' => 1,
            'publisherId' => 3,
            'siteId' => null,
            'status' => self::KEYWORD_ACCEPTED,
        ],
        '2' => [
            'bannerId' => 2,
            'publisherId' => 3,
            'siteId' => null,
            'status' => self::KEYWORD_DECLINED,
        ],
        '3' => [
            'bannerId' => 3,
            'publisherId' => 3,
            'siteId' => null,
            'status' => self::KEYWORD_DECLINED,
        ],
        '4' => [
            'bannerId' => 4,
            'publisherId' => 3,
            'siteId' => null,
            'status' => self::KEYWORD_DECLINED,
        ],
        '5' => [
            'bannerId' => 5,
            'publisherId' => 3,
            'siteId' => null,
            'status' => self::KEYWORD_ACCEPTED,
        ],
        '85' => [
            'bannerId' => 85,
            'publisherId' => 3,
            'siteId' => null,
            'status' => self::KEYWORD_DECLINED,
        ],
    ];

    public function __construct(string $keyword)
    {
        $this->keyword = $keyword;
    }

    public function fetch(int $bannerId): ClassificationCollection
    {
        if (!array_key_exists($bannerId, $this->banners)) {
            throw new BannerNotVerifiedException(sprintf('Banner %s does not exist.', $bannerId));
        }

        $dummy = $this->banners[$bannerId];
        $classification = new Classification(
            $this->keyword,
            $dummy['publisherId'],
            $dummy['status'],
            $dummy['siteId']
        );

        return new ClassificationCollection($classification);
    }
}
