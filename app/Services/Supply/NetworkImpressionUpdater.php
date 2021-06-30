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

namespace Adshares\Adserver\Services\Supply;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkImpression;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Exception\Exception;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Dto\ImpressionContextException;
use Adshares\Supply\Application\Dto\UserContext;
use DateTime;
use Illuminate\Support\Facades\Log;

use function count;
use function get_class;
use function sprintf;

class NetworkImpressionUpdater
{
    private const PACKAGE_SIZE = 500;

    public const TIME_NEEDED_FOR_ADUSER_USER_MERGE = '-10 minutes';

    /** @var AdUser */
    private $adUser;

    public function __construct(AdUser $adUser)
    {
        $this->adUser = $adUser;
    }

    private static function setLastUpdatedId(int $id): void
    {
        Config::upsertInt(Config::LAST_UPDATED_IMPRESSION_ID, $id);
    }

    public static function getLastUpdatedId(): int
    {
        return Config::fetchInt(Config::LAST_UPDATED_IMPRESSION_ID);
    }

    public function updateWithAdUserData(): int
    {
        $idFrom = $this->getLastUpdatedId() + 1;
        $idTo =
            NetworkImpression::where('id', '>=', $idFrom)->where(
                'created_at',
                '<=',
                new DateTime(self::TIME_NEEDED_FOR_ADUSER_USER_MERGE)
            )->max('id');

        if (null === $idTo) {
            return 0;
        }

        $updated = 0;

        do {
            $impressions =
                NetworkImpression::whereBetween('id', [$idFrom, $idTo])->take(self::PACKAGE_SIZE)->get();
            $n = count($impressions);

            foreach ($impressions as $impression) {
                try {
                    /** @var $impression NetworkImpression */
                    $impression->updateWithUserContext($this->userContext($this->adUser, $impression));
                    $updated++;
                } catch (ImpressionContextException | RuntimeException $e) {
                    Log::error(
                        sprintf(
                            '%s {"command":"%s","impression_id":"%d","error":"%s"}',
                            get_class($e),
                            __FUNCTION__,
                            $impression->id,
                            Exception::cleanMessage($e->getMessage())
                        )
                    );
                }
            }

            if ($n > 0) {
                $idFrom = $impressions->last()->id;
                $this->setLastUpdatedId($idFrom);
                $idFrom++;
            }
        } while (count($impressions) === self::PACKAGE_SIZE);

        return $updated;
    }

    private function userContext(AdUser $adUser, NetworkImpression $impression): UserContext
    {
        static $userInfoCache = [];

        $impressionContext = ImpressionContext::fromEventData(
            $impression->context,
            $impression->tracking_id
        );
        $trackingId = $impressionContext->trackingId();

        if (isset($userInfoCache[$trackingId])) {
            return $userInfoCache[$trackingId];
        }

        $userContext = $adUser->getUserContext($impressionContext);

        Log::debug(
            sprintf(
                '%s {"userInfoCache":"MISS","humanScore":%s,"event":%s,"trackingId":%s,"context": %s}',
                __FUNCTION__,
                $userContext->humanScore(),
                $impression->id,
                $impression->tracking_id,
                $userContext->toString()
            )
        );

        $userInfoCache[$trackingId] = $userContext;

        return $userContext;
    }
}
