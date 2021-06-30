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

namespace Adshares\Supply\Application\Dto;

use Adshares\Common\Application\Service\AdUser;

use function array_map;
use function array_merge;
use function is_array;
use function json_encode;

final class UserContext
{
    /** @var array */
    private $keywords;

    /** @var float */
    private $humanScore;

    /** @var float */
    private $pageRank;

    /** @var string */
    private $pageRankInfo;

    /** @var string */
    private $userId;

    public function __construct(
        array $keywords,
        float $humanScore,
        float $pageRank,
        string $pageRankInfo,
        string $userId
    ) {
        $this->keywords = $keywords;
        $this->humanScore = $humanScore;
        $this->pageRank = $pageRank;
        $this->pageRankInfo = $pageRankInfo;
        $this->userId = $userId;
    }

    public static function fromAdUserArray(array $body): self
    {
        return new self(
            self::arrayify($body['keywords'] ?? []),
            (float)($body['human_score'] ?? AdUser::HUMAN_SCORE_ON_MISSING_FIELD),
            (float)($body['page_rank'] ?? AdUser::PAGE_RANK_ON_MISSING_FIELD),
            (string)($body['page_rank_info'] ?? AdUser::PAGE_INFO_UNKNOWN),
            $body['uuid'] ?? ''
        );
    }

    private static function arrayify(array $array): array
    {
        $function = static function ($item) {
            return is_array($item) ? $item : [$item];
        };

        return array_map($function, $array);
    }

    public function toAdSelectPartialArray(): array
    {
        $keywords = array_merge(
            $this->keywords,
            [
                'human_score' => [$this->humanScore],
                'page_rank' => [$this->pageRank],
            ]
        );

        return [
            'uid' => $this->userId,
            'keywords' => $keywords,
        ];
    }

    public function toString(): string
    {
        return json_encode([
            'uid' => $this->userId,
            'keywords' => $this->keywords,
            'human_score' => $this->humanScore,
            'page_rank' => $this->pageRank,
            'page_rank_info' => $this->pageRankInfo,
        ]) ?: '-';
    }

    public function keywords(): array
    {
        return $this->keywords;
    }

    public function country(): ?string
    {
        return $this->keywords['user']['country'] ?? null;
    }

    public function humanScore(): float
    {
        return $this->humanScore;
    }

    public function pageRank(): float
    {
        return $this->pageRank;
    }

    public function pageRankInfo(): string
    {
        return $this->pageRankInfo;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function isCrawler(): bool
    {
        return !!($this->keywords['device']['crawler'] ?? false);
    }
}
