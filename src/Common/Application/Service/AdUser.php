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

namespace Adshares\Common\Application\Service;

use Adshares\Common\Application\Dto\PageRank;
use Adshares\Common\Application\Dto\Taxonomy;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Dto\UserContext;

interface AdUser
{
    public const HUMAN_SCORE_ON_CONNECTION_ERROR = 0.40;
    public const HUMAN_SCORE_ON_MISSING_FIELD = 0.41;
    public const HUMAN_SCORE_ON_MISSING_KEYWORD = 0.42;
    public const HUMAN_SCORE_ON_MISSING_TID = 0.43;
    public const HUMAN_SCORE_MINIMUM = 0.45;

    public const CPA_ONLY_PAGE_RANK = -1.0;

    public const PAGE_RANK_ON_CONNECTION_ERROR = -1.0;
    public const PAGE_RANK_ON_MISSING_FIELD = 0.0;
    public const PAGE_RANK_ON_MISSING_KEYWORD = 0.0;
    public const PAGE_RANK_ON_MISSING_TID = 0.0;

    public const PAGE_INFO_OK = 'ok';
    public const PAGE_INFO_UNKNOWN = 'unknown';
    public const PAGE_INFO_HIGH_IVR = 'high-ivr';
    public const PAGE_INFO_HIGH_CTR = 'high-ctr';
    public const PAGE_INFO_LOW_CTR = 'low-ctr';
    public const PAGE_INFO_POOR_TRAFFIC = 'poor-traffic';
    public const PAGE_INFO_POOR_CONTENT = 'poor-content';
    public const PAGE_INFO_SUSPICIOUS_DOMAIN = 'suspicious-domain';
    public const PAGE_INFO_NOT_WORKING = 'not-working';

    public const REASSESSMENT_STATE_INVALID_URL = 'invalid URL';
    public const REASSESSMENT_STATE_PROCESSING = 'processing';
    public const REASSESSMENT_STATE_LOCKED = 'locked';
    public const REASSESSMENT_STATE_ACCEPTED = 'accepted';
    public const REASSESSMENT_STATE_ERROR = 'error';
    public const REASSESSMENT_STATE_NOT_REGISTERED = 'not registered';

    public const PAGE_INFOS = [
        AdUser::PAGE_INFO_OK,
        AdUser::PAGE_INFO_UNKNOWN,
        AdUser::PAGE_INFO_HIGH_IVR,
        AdUser::PAGE_INFO_HIGH_CTR,
        AdUser::PAGE_INFO_LOW_CTR,
        AdUser::PAGE_INFO_POOR_TRAFFIC,
        AdUser::PAGE_INFO_POOR_CONTENT,
        AdUser::PAGE_INFO_SUSPICIOUS_DOMAIN,
        AdUser::PAGE_INFO_NOT_WORKING
    ];

    public function fetchPageRank(string $url): PageRank;

    public function fetchPageRankBatch(array $urls): array;

    public function fetchTargetingOptions(): Taxonomy;

    public function getUserContext(ImpressionContext $context): UserContext;

    public function reassessPageRankBatch(array $urls): array;
}
