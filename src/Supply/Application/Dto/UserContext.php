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

namespace Adshares\Supply\Application\Dto;

use Adshares\Adserver\Http\Utils;
use InvalidArgumentException;

final class UserContext
{
    /** @var array */
    private $keywords;

    /** @var float */
    private $humanScore;

    /** @var string */
    private $userId;

    public function __construct(array $keywords, float $humanScore, string $userId)
    {
        $this->keywords = $keywords;
        $this->humanScore = $humanScore;
        $this->userId = $userId;
        $this->failIfInvalid();
    }

    private function failIfInvalid(): void
    {
        if (!Utils::validTrackingId($this->userId, config('app.adserver_secret'))) {
            throw new InvalidArgumentException('Invalid UID');
        }
    }

    public static function fromAdUserArray(array $context): self
    {
        return new self(
            $context['keywords'],
            (float) $context['human_score'],
            $context['uid']
        );
    }

    public function toAdSelectPartialArray(): array
    {
        return ['uid' => $this->userId, 'keywords' => $this->keywords];
    }

    public function toArray(): array
    {
        return ['uid' => $this->userId, 'keywords' => $this->keywords, 'human_score' => $this->humanScore];
    }
}
