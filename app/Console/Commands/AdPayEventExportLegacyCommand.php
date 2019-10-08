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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Client\Mapper\AdPay\DemandEventMapper;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\EventLog;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Exception\Exception;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Demand\Application\Service\AdPayLegacy;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Dto\ImpressionContextException;
use Adshares\Supply\Application\Dto\UserContext;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use function sprintf;

class AdPayEventExportLegacyCommand extends BaseCommand
{
    private const EVENTS_BUNDLE_MAXIMAL_SIZE = 500;

    protected $signature = 'legacy:ops:adpay:event:export {--first=} {--last=}';

    protected $description = '(Legacy) Exports event data to AdPay';

    public function handle(AdPayLegacy $adPay, AdUser $adUser): void
    {
        $eventIdFirst = $this->option('first');
        $eventIdLast = $this->option('last');

        if ($eventIdLast === null && !$this->lock()) {
            $this->info('[AdPayEventExport] Command '.$this->signature.' already running.');

            return;
        }

        $timeStart = microtime(true);
        $this->info('Start command '.$this->signature);

        $configEventIdFirst = Config::fetchInt('adpay-last-exported-event-id') + 1;

        if ($eventIdFirst === null) {
            $eventIdFirst = $configEventIdFirst;
        }
        if ($eventIdLast !== null) {
            $eventIdLast = (int)$eventIdLast;
        }

        $counter = 0;
        do {
            $eventsToExport = $this->fetchEventsToExport((int)$eventIdFirst, $eventIdLast);

            $this->info(sprintf(
                '[AdPayEventExport] Found %d events to export [%d].',
                count($eventsToExport),
                ++$counter
            ));

            if (count($eventsToExport) > 0) {
                $this->updateEventLogWithAdUserData($adUser, $eventsToExport);

                $events = DemandEventMapper::mapEventCollectionToEventArray($eventsToExport);

                $exportedCount = $this->exportWithoutRequestEvents($adPay, $events);

                $this->info(sprintf(
                    '[AdPayEventExport] Really exported %d events.',
                    $exportedCount
                ));

                $eventIdLastExported = $eventsToExport->last()->id;
                if ($eventIdLastExported > $configEventIdFirst) {
                    Config::upsertInt('adpay-last-exported-event-id', $eventIdLastExported);
                }

                $eventIdFirst = $eventIdLastExported + 1;
            }
        } while (self::EVENTS_BUNDLE_MAXIMAL_SIZE === count($eventsToExport));

        $this->info(sprintf('[AdPayEventExport] LastExportedId %s', $eventIdLastExported ?? '<none>'));
        $this->info('[AdPayEventExport] Finish command '.$this->signature);
        $executionTime = microtime(true) - $timeStart;
        $this->info(sprintf('[AdPayEventExport] Export took %d seconds', (int)$executionTime));
    }

    private function fetchEventsToExport(int $eventIdFirst, ?int $eventIdLast = null): Collection
    {
        $builder = EventLog::where('id', '>=', $eventIdFirst);
        if ($eventIdLast !== null) {
            $builder = $builder->where('id', '<=', $eventIdLast);
        } else {
            $builder = $builder->where('created_at', '<=', new DateTime('-10 minutes'));
        }

        return $builder->orderBy('id')
            ->limit(self::EVENTS_BUNDLE_MAXIMAL_SIZE)
            ->get();
    }

    private function updateEventLogWithAdUserData(AdUser $adUser, Collection $eventsToExport): void
    {
        foreach ($eventsToExport as $event) {
            /** @var $event EventLog */

            if ($event->human_score !== null && $event->our_userdata !== null) {
                continue;
            }

            try {
                $event->updateWithUserContext($this->userContext($adUser, $event));
                $event->save();
            } catch (ImpressionContextException|RuntimeException $e) {
                Log::error(
                    sprintf(
                        '%s {"command":"%s","event":"%d","error":"%s"}',
                        get_class($e),
                        $this->signature,
                        $event->id,
                        Exception::cleanMessage($e->getMessage())
                    )
                );
            }
        }
    }

    private function userContext(AdUser $adUser, EventLog $event): UserContext
    {
        static $userInfoCache = [];

        $impressionContext = ImpressionContext::fromEventData(
            $event->their_context,
            $event->tracking_id
        );
        $trackingId = $impressionContext->trackingId();

        if (isset($userInfoCache[$trackingId])) {
            return $userInfoCache[$trackingId];
        }

        $userContext = $adUser->getUserContext($impressionContext);

        Log::debug(sprintf(
            '%s {"userInfoCache":"MISS","humanScore":%s,"event":%s,"trackingId":%s,"context": %s}',
            __FUNCTION__,
            $userContext->humanScore(),
            $event->id,
            $event->tracking_id,
            $userContext->toString()
        ));

        $userInfoCache[$trackingId] = $userContext;

        return $userContext;
    }

    private function exportWithoutRequestEvents(AdPayLegacy $adPay, array $events): int
    {
        if (empty($events)) {
            return 0;
        }

        $adPay->addEvents($events);

        return count($events);
    }
}
