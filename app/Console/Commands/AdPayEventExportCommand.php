<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Client\Mapper\AdPay\DemandEventMapper;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Conversion;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Services\Common\AdsTxtCrawler;
use Adshares\Adserver\ViewModel\MediumName;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Exception\Exception;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Demand\Application\Dto\AdPayEvents;
use Adshares\Demand\Application\Service\AdPay;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Dto\UserContext;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Fork\Fork;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\Store\FlockStore;
use Throwable;

class AdPayEventExportCommand extends BaseCommand
{
    private const EVENTS_BUNDLE_MAXIMAL_SIZE = 500;

    private const EVENTS_PERIOD = 5 * 60;

    private const DEFAULT_EXPORT_TIME_FROM = '-2 hours';

    private const DEFAULT_EXPORT_TIME_TO = '-10 minutes';

    private const ADS_TXT_TTL_VALID = 3600;

    private const ADS_TXT_TTL_INVALID = 86400;

    private const MAXIMAL_THREAD_RETRIES = 3;

    protected $signature = 'ops:adpay:event:export {--from=} {--to=} {--t|threads=4}';

    protected $description = 'Exports event data to AdPay';

    public function __construct(
        private readonly AdPay $adPay,
        private readonly AdsTxtCrawler $adsTxtCrawler,
        private readonly AdUser $adUser,
        private readonly Locker $locker,
    ) {
        parent::__construct($this->locker);
    }

    public function handle(): int
    {
        $commandStartTime = microtime(true);

        $optionFrom = $this->option('from');
        $optionTo = $this->option('to');
        $isCommandExecutedAutomatically = null === $optionTo;

        $lock = new Lock(new Key($this->getName()), new FlockStore(), null, false);
        if (null === $optionTo && !$lock->acquire()) {
            $this->info('[AdPayEventExport] Command ' . $this->signature . ' already running.');
            return self::FAILURE;
        }

        if (null === $optionFrom && null !== $optionTo) {
            $this->error('[AdPayEventExport] Option --from must be defined when option --to is defined');
            $lock->release();
            return self::FAILURE;
        }

        $this->info('[AdPayEventExport] Start command ' . $this->signature);

        if (null !== $optionFrom) {
            if (false === ($timestampFrom = strtotime($optionFrom))) {
                $this->error(sprintf('[AdPayEventExport] Invalid option --from format "%s"', $optionFrom));
                $lock->release();
                return self::FAILURE;
            }

            $dateFromEvents = new DateTimeImmutable('@' . $this->correctUserTimestamp($timestampFrom));
            $dateFromConversions = $dateFromEvents;
        } else {
            $dateFromEvents = DateTimeImmutable::createFromMutable(
                Config::fetchDateTime(
                    Config::ADPAY_LAST_EXPORTED_EVENT_TIME,
                    new DateTime(self::DEFAULT_EXPORT_TIME_FROM)
                )
            );
            $dateFromConversions = DateTimeImmutable::createFromMutable(
                Config::fetchDateTime(
                    Config::ADPAY_LAST_EXPORTED_CONVERSION_TIME,
                    new DateTime(self::DEFAULT_EXPORT_TIME_FROM)
                )
            );
        }

        if (null !== $optionTo) {
            if (false === ($timestampTo = strtotime($optionTo))) {
                $this->error(sprintf('[AdPayEventExport] Invalid option --to format "%s"', $optionTo));
                $lock->release();
                return self::FAILURE;
            }

            $dateTo = new DateTimeImmutable('@' . $timestampTo);
        } else {
            $dateTo = new DateTimeImmutable(self::DEFAULT_EXPORT_TIME_TO);
        }

        if ($dateFromEvents >= $dateTo) {
            $this->error(
                sprintf(
                    '[AdPayEventExport] Invalid events range from %s (exclusive) to %s (inclusive)',
                    $dateFromEvents->format(DateTimeInterface::ATOM),
                    $dateTo->format(DateTimeInterface::ATOM)
                )
            );
            $lock->release();
            return self::FAILURE;
        }

        if ($dateFromConversions >= $dateTo) {
            $this->error(
                sprintf(
                    '[AdPayEventExport] Invalid conversions range from %s (exclusive) to %s (inclusive)',
                    $dateFromConversions->format(DateTimeInterface::ATOM),
                    $dateTo->format(DateTimeInterface::ATOM)
                )
            );
            $lock->release();
            return self::FAILURE;
        }

        try {
            $this->exportEvents($dateFromEvents, $dateTo, $isCommandExecutedAutomatically);
            $this->exportConversions($dateFromConversions, $dateTo, $isCommandExecutedAutomatically);
        } catch (RuntimeException $exception) {
            $this->error(sprintf('[AdPayEventExport] Export failed: %s', $exception->getMessage()));
            $lock->release();
            return self::FAILURE;
        }

        $commandExecutionTime = microtime(true) - $commandStartTime;
        $this->info(sprintf('[AdPayEventExport] Finished after %d seconds', (int)$commandExecutionTime));
        $lock->release();
        return self::SUCCESS;
    }

    /**
     * @param Collection<EventLog> $events
     * @return void
     */
    private function updateEventsWithExternalData(Collection $events): void
    {
        $checkAdsTxt = config('app.ads_txt_check_demand_enabled');
        foreach ($events as $event) {
            try {
                if (null === $event->human_score || null === $event->our_userdata) {
                    $event->updateWithUserContext($this->userContext($event));
                }
                if (
                    $checkAdsTxt
                    && MediumName::Web->value === $event->medium
                    && null === $event->ads_txt
                    && null !== $event->domain
                ) {
                    $event->ads_txt = (int)$this->checkAdsTxtOfEvent($event);
                }
                if ($event->isDirty()) {
                    $event->save();
                }
            } catch (RuntimeException $exception) {
                Log::error(
                    sprintf(
                        '%s {"command":"%s","event":"%d","error":"%s"}',
                        get_class($exception),
                        $this->signature,
                        $event->id,
                        Exception::cleanMessage($exception->getMessage())
                    )
                );
            }
        }
    }

    private function userContext(EventLog $event): UserContext
    {
        $impressionContext = ImpressionContext::fromEventData(
            $event->their_context,
            $event->tracking_id
        );
        return $this->adUser->getUserContext($impressionContext);
    }

    private function exportEvents(
        DateTimeImmutable $dateFrom,
        DateTimeImmutable $dateTo,
        bool $isCommandExecutedAutomatically
    ): void {
        $periodTotal = $dateTo->getTimestamp() - $dateFrom->getTimestamp();
        $periodCount = (int)ceil($periodTotal / self::EVENTS_PERIOD);

        $dateToChunk = $dateFrom;
        for ($i = 0; $i < $periodCount; $i++) {
            $dateFromChunk = $dateToChunk;
            $dateToChunk = $dateToChunk->modify(sprintf('+%d seconds', self::EVENTS_PERIOD));
            if ($dateToChunk > $dateTo) {
                $dateToChunk = $dateTo;
            }

            $this->exportEventsInPacks($dateFromChunk, $dateToChunk, $isCommandExecutedAutomatically);
        }
    }

    private function exportConversions(
        DateTimeImmutable $dateFrom,
        DateTimeImmutable $dateTo,
        bool $isCommandExecutedAutomatically
    ): void {
        $periodTotal = $dateTo->getTimestamp() - $dateFrom->getTimestamp();
        $periodCount = (int)ceil($periodTotal / self::EVENTS_PERIOD);

        $dateToChunk = $dateFrom;
        for ($i = 0; $i < $periodCount; $i++) {
            $dateFromChunk = $dateToChunk;
            $dateToChunk = $dateToChunk->modify(sprintf('+%d seconds', self::EVENTS_PERIOD));
            if ($dateToChunk > $dateTo) {
                $dateToChunk = $dateTo;
            }

            $this->exportConversionsInPacks($dateFromChunk, $dateToChunk, $isCommandExecutedAutomatically);
        }
    }

    private function exportEventsInPacks(
        DateTimeImmutable $dateFrom,
        DateTimeImmutable $dateTo,
        bool $isCommandExecutedAutomatically
    ): void {
        $this->info(
            sprintf(
                '[AdPayEventExport] Exporting events from %s (exclusive) to %s (inclusive)',
                $dateFrom->format(DateTimeInterface::ATOM),
                $dateTo->format(DateTimeInterface::ATOM)
            )
        );

        $seconds = $dateTo->getTimestamp() - $dateFrom->getTimestamp();
        $eventsCount = EventLog::where('created_at', '>', $dateFrom)->where('created_at', '<=', $dateTo)->count();
        $packCount = max((int)ceil($eventsCount / self::EVENTS_BUNDLE_MAXIMAL_SIZE), 1);
        if ($seconds < $packCount) {
            $this->error(
                sprintf('[AdPayEventExport] Too many events to export (%s) in time (%s)', $eventsCount, $seconds)
            );
            return;
        }
        $packInterval = (int)ceil($seconds / $packCount);

        $dateToTemporary = $dateFrom;

        $threads = [];
        for ($pack = 0; $pack < $packCount; $pack++) {
            $dateFromTemporary = $dateToTemporary;
            $dateToTemporary = $dateToTemporary->modify(sprintf('+%d seconds', $packInterval));

            if ($dateToTemporary > $dateTo) {
                $dateToTemporary = $dateTo;
            }

            if ($dateFromTemporary >= $dateToTemporary) {
                break;
            }

            $threads[] = function () use ($dateFromTemporary, $dateToTemporary, $pack) {
                for ($attempt = 0; $attempt < self::MAXIMAL_THREAD_RETRIES; $attempt++) {
                    try {
                        $this->exportEventsPack($pack, $dateFromTemporary, $dateToTemporary);
                        return self::SUCCESS;
                    } catch (Throwable $throwable) {
                        Log::error(sprintf(
                            '[AdPayEventExport] Error during exporting pack %d (attempt: %d, %s -> %s, %s s) %s',
                            $pack + 1,
                            $attempt + 1,
                            $dateFromTemporary->format(DateTimeInterface::ATOM),
                            $dateToTemporary->format(DateTimeInterface::ATOM),
                            $dateToTemporary->getTimestamp() - $dateFromTemporary->getTimestamp(),
                            $throwable->getMessage(),
                        ));
                    }
                }
                return self::FAILURE;
            };
        }

        if (count($threads) === 1) {
            call_user_func(reset($threads));
        } else {
            $results = Fork::new()
                ->concurrent((int)$this->option('threads'))
                ->before(fn () => DB::connection()->reconnect())
                ->after(parent: DB::connection()->reconnect())
                ->run(...$threads);
            if (in_array(self::FAILURE, $results, true)) {
                throw new RuntimeException('Cannot export events');
            }
        }

        if ($isCommandExecutedAutomatically && $packCount > 0) {
            Config::upsertDateTime(Config::ADPAY_LAST_EXPORTED_EVENT_TIME, $dateTo);
        }

        $this->info(sprintf('[AdPayEventExport] Finished exporting %d events', $eventsCount));
    }

    private function exportEventsPack(
        int $pack,
        DateTimeImmutable $dateFrom,
        DateTimeImmutable $dateTo
    ): void {
        $eventsToExport =
            EventLog::where('created_at', '>', $dateFrom)
                ->where('created_at', '<=', $dateTo)
                ->get();

        Log::debug(
            sprintf(
                '[AdPayEventExport] Pack [%d]. Events to export: %d (%s -> %s, %s s)',
                $pack + 1,
                count($eventsToExport),
                $dateFrom->format(DateTimeInterface::ATOM),
                $dateTo->format(DateTimeInterface::ATOM),
                $dateTo->getTimestamp() - $dateFrom->getTimestamp()
            )
        );

        $this->updateEventsWithExternalData($eventsToExport);

        $timeStart = $dateFrom->modify('+1 second');
        $timeEnd = $dateTo;
        if ($timeStart > $timeEnd) {
            $timeEnd = $timeStart;
        }

        $views = DemandEventMapper::mapEventCollectionToArray(
            $eventsToExport->filter(
                function ($item) {
                    return EventLog::TYPE_VIEW === $item->event_type;
                }
            )
        );
        $this->adPay->addViews(new AdPayEvents($timeStart, $timeEnd, $views));

        $clicks = DemandEventMapper::mapEventCollectionToArray(
            $eventsToExport->filter(
                function ($item) {
                    return EventLog::TYPE_CLICK === $item->event_type;
                }
            )
        );
        $this->adPay->addClicks(new AdPayEvents($timeStart, $timeEnd, $clicks));
    }

    private function exportConversionsInPacks(
        DateTimeImmutable $dateFrom,
        DateTimeImmutable $dateTo,
        bool $isCommandExecutedAutomatically
    ): void {
        $this->info(
            sprintf(
                '[AdPayEventExport] Exporting conversions from %s (exclusive) to %s (inclusive)',
                $dateFrom->format(DateTimeInterface::ATOM),
                $dateTo->format(DateTimeInterface::ATOM)
            )
        );

        $seconds = $dateTo->getTimestamp() - $dateFrom->getTimestamp();
        $eventsCount = Conversion::where('created_at', '>', $dateFrom)->where('created_at', '<=', $dateTo)->count();
        $packCount = max((int)ceil($eventsCount / self::EVENTS_BUNDLE_MAXIMAL_SIZE), 1);
        if ($seconds < $packCount) {
            $this->error(
                sprintf('[AdPayEventExport] Too many conversions to export (%s) in time (%s)', $eventsCount, $seconds)
            );
            return;
        }
        $packInterval = (int)ceil($seconds / $packCount);

        $dateToTemporary = $dateFrom;

        for ($pack = 0; $pack < $packCount; $pack++) {
            $dateFromTemporary = $dateToTemporary;
            $dateToTemporary = $dateToTemporary->modify(sprintf('+%d seconds', $packInterval));

            if ($dateToTemporary > $dateTo) {
                $dateToTemporary = $dateTo;
            }

            if ($dateFromTemporary >= $dateToTemporary) {
                break;
            }

            $conversionsToExport = Conversion::where('created_at', '>', $dateFromTemporary)->where(
                'created_at',
                '<=',
                $dateToTemporary
            )->with('event')->with('conversionDefinition')->get();

            Log::debug(
                sprintf(
                    '[AdPayEventExport] Pack [%d]. Conversions to export: %d (%s -> %s, %s s)',
                    $pack + 1,
                    count($conversionsToExport),
                    $dateFromTemporary->format(DateTimeInterface::ATOM),
                    $dateToTemporary->format(DateTimeInterface::ATOM),
                    $dateToTemporary->getTimestamp() - $dateFromTemporary->getTimestamp()
                )
            );

            $timeStart = $dateFromTemporary->modify('+1 second');
            $timeEnd = $dateToTemporary;
            $conversions = DemandEventMapper::mapConversionCollectionToArray($conversionsToExport);

            $this->adPay->addConversions(new AdPayEvents($timeStart, $timeEnd, $conversions));

            if ($isCommandExecutedAutomatically) {
                Config::upsertDateTime(Config::ADPAY_LAST_EXPORTED_CONVERSION_TIME, $dateToTemporary);
            }
        }

        $this->info(sprintf('[AdPayEventExport] Finished exporting %d conversions', $eventsCount));
    }

    private function correctUserTimestamp(int $timestampFrom): int
    {
        return $timestampFrom - 1;
    }

    private function checkAdsTxtOfEvent(EventLog $event): bool
    {
        $cacheKey = sprintf('ads_txt.%s.%s', $event->publisher_id, $event->domain);
        if (null === ($result = Cache::get($cacheKey))) {
            $payTo = $event->pay_to;
            $adServerDomain = Cache::remember(
                sprintf('network_host.ads_txt_domain.%s', $payTo),
                self::ADS_TXT_TTL_VALID,
                function () use ($payTo) {
                    $networkHost = NetworkHost::fetchByAddress($payTo);
                    return null === $networkHost ? '' : $networkHost->info->getAdsTxtDomain();
                },
            );

            if ('' === $adServerDomain) {
                $result = false;
            } else {
                $result = $this->adsTxtCrawler->checkSite(
                    'https://' . $event->domain,
                    $adServerDomain,
                    $event->publisher_id,
                );
            }
            Cache::put($cacheKey, $result, $result ? self::ADS_TXT_TTL_VALID : self::ADS_TXT_TTL_INVALID);
        }
        return $result;
    }
}
