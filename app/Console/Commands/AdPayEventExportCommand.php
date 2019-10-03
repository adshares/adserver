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
use Adshares\Demand\Application\Service\AdPay;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Dto\ImpressionContextException;
use Adshares\Supply\Application\Dto\UserContext;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use function ceil;
use function sprintf;

class AdPayEventExportCommand extends BaseCommand
{
    private const EVENTS_BUNDLE_MAXIMAL_SIZE = 500;

    protected $signature = 'ops:adpay:event:export {--from=} {--to=}';

    protected $description = 'Exports event data to AdPay';

    public function handle(AdPay $adPay, AdUser $adUser): void
    {
        $commandStartTime = microtime(true);

        $optionFrom = $this->option('from');
        $optionTo = $this->option('to');

        if (null === $optionTo && !$this->lock()) {
            $this->info('[AdPayEventExport] Command '.$this->signature.' already running.');

            return;
        }

        if (null === $optionFrom && null !== $optionTo) {
            $this->error('[AdPayEventExport] Option --from must be defined when option --to is defined');

            return;
        }

        $this->info('[AdPayEventExport] Start command '.$this->signature);

        if (null !== $optionFrom) {
            if (false === ($timestampFrom = strtotime($optionFrom))) {
                $this->error(sprintf('[AdPayEventExport] Invalid option --from format "%s"', $optionFrom));

                return;
            }

            $dateFrom = (new DateTime())->setTimestamp($timestampFrom);
        } else {
            $dateFrom = Config::fetchDateTime(Config::ADPAY_LAST_EXPORTED_EVENT_TIME, new DateTime('-4 hours'));
        }

        if (null !== $optionTo) {
            if (false === ($timestampTo = strtotime($optionTo))) {
                $this->error(sprintf('[AdPayEventExport] Invalid option --to format "%s"', $optionTo));

                return;
            }

            $dateTo = (new DateTime())->setTimestamp($timestampTo);
        } else {
            $dateTo = new DateTime('-10 minutes');
        }

        if ($dateFrom >= $dateTo) {
            $this->error(
                sprintf(
                    '[AdPayEventExport] Invalid range from %s (exclusive) to %s (inclusive)',
                    $dateFrom->format(DateTime::ATOM),
                    $dateTo->format(DateTime::ATOM)
                )
            );

            return;
        }

        $this->exportEvents($adPay, $adUser, $dateFrom, $dateTo, $optionTo);

        $commandExecutionTime = microtime(true) - $commandStartTime;
        $this->info(sprintf('[AdPayEventExport] Finished after %d seconds', (int)$commandExecutionTime));
    }

    private function updateEventLogWithAdUserData(AdUser $adUser, Collection $events): void
    {
        foreach ($events as $event) {
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

        Log::debug(
            sprintf(
                '%s {"userInfoCache":"MISS","humanScore":%s,"event":%s,"trackingId":%s,"context": %s}',
                __FUNCTION__,
                $userContext->humanScore(),
                $event->id,
                $event->tracking_id,
                $userContext->toString()
            )
        );

        $userInfoCache[$trackingId] = $userContext;

        return $userContext;
    }

    private function fetchEvents(DateTime $dateFrom, DateTime $dateTo): array
    {
        return DB::select(
            self::SQL_SELECT_EVENTS_TEMPLATE,
            [
                'date_from1' => $dateFrom,
                'date_to1' => $dateTo,
                'date_from2' => $dateFrom,
                'date_to2' => $dateTo,
                'limit' => self::EVENTS_BUNDLE_MAXIMAL_SIZE,
                'offset' => 0,
            ]
        );
    }

    private const SQL_SELECT_EVENTS_TEMPLATE = <<<SQL
SELECT UNIX_TIMESTAMP(created_at)   AS `timestamp`,
       banner_id,
       case_id,
       event_type,
       event_id,
       their_userdata,
       our_userdata,
       human_score,
       IFNULL(user_id, tracking_id) AS user_id,
       publisher_id,
       event_value,
       reason,
       null                         AS conversion_definition_id
FROM event_logs
WHERE created_at > :date_from1
  AND created_at <= :date_to1
UNION
SELECT UNIX_TIMESTAMP(cg.created_at)                                            AS `timestamp`,
       e.banner_id                                                              AS banner_id,
       cg.case_id                                                               AS case_id,
       'conversion'                                                             AS event_type,
       (SELECT event_id FROM event_conversion_logs WHERE id = cg.event_logs_id) AS event_id,
       e.their_userdata                                                         AS their_userdata,
       e.our_userdata                                                           AS our_userdata,
       e.human_score                                                            AS human_score,
       IFNULL(e.user_id, e.tracking_id)                                         AS user_id,
       e.publisher_id                                                           AS publisher_id,
       cg.value                                                                 AS event_value,
       e.reason                                                                 AS reason,
       cg.conversion_definition_id                                              AS conversion_definition_id
FROM conversion_groups AS cg
       JOIN event_logs e ON (cg.case_id = e.case_id AND e.event_type = 'view')
WHERE cg.created_at > :date_from2
  AND cg.created_at <= :date_to2
LIMIT :limit
  OFFSET :offset
SQL;

    private function exportEvents(AdPay $adPay, AdUser $adUser, DateTime $dateFrom, DateTime $dateTo, $optionTo): void
    {
        $this->info(
            sprintf(
                '[AdPayEventExport] Exporting events from %s (exclusive) to %s (inclusive)',
                $dateFrom->format(DateTime::ATOM),
                $dateTo->format(DateTime::ATOM)
            )
        );

        $seconds = $dateTo->getTimestamp() - $dateFrom->getTimestamp();
        $eventsCount = EventLog::where('created_at', '>', $dateFrom)->where('created_at', '<=', $dateTo)->count();
        $packCount = max((int)ceil($eventsCount / self::EVENTS_BUNDLE_MAXIMAL_SIZE), 1);
        $packInterval = (int)floor($seconds / $packCount);
        if (0 === $packInterval) {
            $this->error(
                sprintf('[AdPayEventExport] Too many events to export (%s) in time (%s)', $eventsCount, $seconds)
            );
        }

        $dateToTemporary = clone $dateFrom;

        for ($pack = 0; $pack < $packCount; $pack++) {
            $dateFromTemporary = clone $dateToTemporary;
            $dateToTemporary->modify(sprintf('+ %d seconds', $packInterval));

            if ($dateToTemporary > $dateTo) {
                $dateToTemporary = clone $dateTo;
            }

            $eventsToExport =
                EventLog::where('created_at', '>', $dateFromTemporary)
                    ->where('created_at', '<=', $dateToTemporary)
                    ->get();

            $this->info(
                sprintf('[AdPayEventExport] Pack [%d]. Events to export: %d', $pack + 1, count($eventsToExport))
            );

            $this->updateEventLogWithAdUserData($adUser, $eventsToExport);
            $events = DemandEventMapper::mapEventCollectionToEventArray($eventsToExport);

            $request = [
                'meta' => [
                    'from' => $dateFromTemporary->format(DateTime::ATOM),
                    'to' => $dateToTemporary->format(DateTime::ATOM),
                ],
                'events' => $events,
            ];

            $adPay->addEvents($request);

            if (null === $optionTo && count($eventsToExport) > 0) {
                Config::upsertDateTime(Config::ADPAY_LAST_EXPORTED_EVENT_TIME, $dateToTemporary);
            }
        }

        $this->info(sprintf('[AdPayEventExport] Finished exporting %d events', $eventsCount));
    }
}
