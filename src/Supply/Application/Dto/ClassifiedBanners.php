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

namespace Adshares\Supply\Application\Dto;

use Adshares\Supply\Domain\ValueObject\Classification;
use function array_key_exists;

class ClassifiedBanners
{
    private $banners;

    public function __construct(array $classifiedBanners)
    {
        foreach ($classifiedBanners as $bannerId => $classification) {
            if ($classification === null) {
                $this->banners[$bannerId] = null;

                continue;
            }

            if (array_key_exists('keywords', $classifiedBanners)) {

            }

            if (array_key_exists('signature', $classifiedBanners)) {

            }

            $this->banners[$bannerId] = new Classification($classification['keywords'], $classification['signature']);
        }
    }

    public function findByBannerId(string $bannerId): ?Classification
    {
        if (!array_key_exists($bannerId, $this->banners)) {

        }

        return $this->banners[$bannerId];
    }
}
