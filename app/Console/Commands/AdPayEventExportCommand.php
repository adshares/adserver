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
use Adshares\Adserver\Models\Conversion;
use Adshares\Adserver\Models\EventLog;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Exception\Exception;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Demand\Application\Dto\AdPayEvents;
use Adshares\Demand\Application\Service\AdPay;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Dto\ImpressionContextException;
use Adshares\Supply\Application\Dto\UserContext;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use function ceil;
use function sprintf;

class AdPayEventExportCommand extends BaseCommand
{
    private const EVENTS_BUNDLE_MAXIMAL_SIZE = 500;

    private const DEFAULT_EXPORT_TIME_FROM = '-4 hours';

    private const DEFAULT_EXPORT_TIME_TO = '-10 minutes';

    protected $signature = 'ops:adpay:event:export {--from=} {--to=}';

    protected $description = 'Exports event data to AdPay';

    public function handle(AdPay $adPay, AdUser $adUser): void
    {
        $commandStartTime = microtime(true);

        $optionFrom = $this->option('from');
        $optionTo = $this->option('to');
        $isCommandExecutedAutomatically = null === $optionTo;

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

            $dateFromEvents = (new DateTime())->setTimestamp($timestampFrom);
            $dateFromConversions = clone $dateFromEvents;
        } else {
            $dateFromEvents =
                Config::fetchDateTime(
                    Config::ADPAY_LAST_EXPORTED_EVENT_TIME,
                    new DateTime(self::DEFAULT_EXPORT_TIME_FROM)
                );
            $dateFromConversions =
                Config::fetchDateTime(
                    Config::ADPAY_LAST_EXPORTED_CONVERSION_TIME,
                    new DateTime(self::DEFAULT_EXPORT_TIME_FROM)
                );
        }

        if (null !== $optionTo) {
            if (false === ($timestampTo = strtotime($optionTo))) {
                $this->error(sprintf('[AdPayEventExport] Invalid option --to format "%s"', $optionTo));

                return;
            }

            $dateTo = (new DateTime())->setTimestamp($timestampTo);
        } else {
            $dateTo = new DateTime(self::DEFAULT_EXPORT_TIME_TO);
        }

        $this->exportEvents($adPay, $adUser, $dateFromEvents, $dateTo, $isCommandExecutedAutomatically);
        $this->exportConversions($adPay, $dateFromConversions, $dateTo, $isCommandExecutedAutomatically);

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

    private function exportEvents(
        AdPay $adPay,
        AdUser $adUser,
        DateTime $dateFrom,
        DateTime $dateTo,
        bool $isCommandExecutedAutomatically
    ): void {
        if ($dateFrom >= $dateTo) {
            $this->error(
                sprintf(
                    '[AdPayEventExport] Invalid events range from %s (exclusive) to %s (inclusive)',
                    $dateFrom->format(DateTime::ATOM),
                    $dateTo->format(DateTime::ATOM)
                )
            );

            return;
        }

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

            return;
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

            $views = DemandEventMapper::mapEventCollectionToArray(
                $eventsToExport->filter(
                    function ($item) {
                        return EventLog::TYPE_VIEW === $item->event_type;
                    }
                )
            );
            $clicks = DemandEventMapper::mapEventCollectionToArray(
                $eventsToExport->filter(
                    function ($item) {
                        return EventLog::TYPE_CLICK === $item->event_type;
                    }
                )
            );

            $timeStart = (clone $dateFromTemporary)->modify('+1 second');
            $timeEnd = clone $dateToTemporary;
            $adPay->addViews(new AdPayEvents($timeStart, $timeEnd, $views));
            $adPay->addClicks(new AdPayEvents($timeStart, $timeEnd, $clicks));

            if ($isCommandExecutedAutomatically && count($eventsToExport) > 0) {
                Config::upsertDateTime(Config::ADPAY_LAST_EXPORTED_EVENT_TIME, $dateToTemporary);
            }
        }

        $this->info(sprintf('[AdPayEventExport] Finished exporting %d events', $eventsCount));
    }

    private function exportConversions(
        AdPay $adPay,
        DateTime $dateFrom,
        DateTime $dateTo,
        bool $isCommandExecutedAutomatically
    ): void {
        if ($dateFrom >= $dateTo) {
            $this->error(
                sprintf(
                    '[AdPayEventExport] Invalid conversions range from %s (exclusive) to %s (inclusive)',
                    $dateFrom->format(DateTime::ATOM),
                    $dateTo->format(DateTime::ATOM)
                )
            );

            return;
        }

        $this->info(
            sprintf(
                '[AdPayEventExport] Exporting conversions from %s (exclusive) to %s (inclusive)',
                $dateFrom->format(DateTime::ATOM),
                $dateTo->format(DateTime::ATOM)
            )
        );

        $seconds = $dateTo->getTimestamp() - $dateFrom->getTimestamp();
        $eventsCount = Conversion::where('created_at', '>', $dateFrom)->where('created_at', '<=', $dateTo)->count();
        $packCount = max((int)ceil($eventsCount / self::EVENTS_BUNDLE_MAXIMAL_SIZE), 1);
        $packInterval = (int)floor($seconds / $packCount);
        if (0 === $packInterval) {
            $this->error(
                sprintf('[AdPayEventExport] Too many conversions to export (%s) in time (%s)', $eventsCount, $seconds)
            );

            return;
        }

        $dateToTemporary = clone $dateFrom;

        for ($pack = 0; $pack < $packCount; $pack++) {
            $dateFromTemporary = clone $dateToTemporary;
            $dateToTemporary->modify(sprintf('+ %d seconds', $packInterval));

            if ($dateToTemporary > $dateTo) {
                $dateToTemporary = clone $dateTo;
            }

            $conversionsToExport = Conversion::where('created_at', '>', $dateFromTemporary)->where(
                'created_at',
                '<=',
                $dateToTemporary
            )->with('event')->get();

            $this->info(
                sprintf(
                    '[AdPayEventExport] Pack [%d]. Conversions to export: %d',
                    $pack + 1,
                    count($conversionsToExport)
                )
            );

            $conversions = DemandEventMapper::mapConversionCollectionToArray($conversionsToExport);

            $timeStart = (clone $dateFromTemporary)->modify('+1 second');
            $timeEnd = clone $dateToTemporary;
            $adPay->addConversions(new AdPayEvents($timeStart, $timeEnd, $conversions));

            if ($isCommandExecutedAutomatically && count($conversionsToExport) > 0) {
                Config::upsertDateTime(Config::ADPAY_LAST_EXPORTED_CONVERSION_TIME, $dateToTemporary);
            }
        }

        $this->info(sprintf('[AdPayEventExport] Finished exporting %d conversions', $eventsCount));
    }
}
