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

namespace Adshares\Common\Application\Service;

use Adshares\Common\Application\Dto\Taxonomy;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Dto\UserContext;

interface AdUser
{
    public const HUMAN_SCORE_ON_CONNECTION_ERROR = 0.40;
    public const HUMAN_SCORE_ON_MISSING_FIELD = 0.41;
    public const HUMAN_SCORE_ON_MISSING_KEYWORD = 0.42;
    public const HUMAN_SCORE_MINIMUM = 0.45;

    public function fetchTargetingOptions(): Taxonomy;

    public function getUserContext(ImpressionContext $context): UserContext;
}
