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

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkEventLog;
use Adshares\Adserver\Repository\Supply\NetworkEventRepository;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Exception\Exception;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Dto\ImpressionContextException;
use Adshares\Supply\Application\Dto\UserContext;
use Adshares\Supply\Application\Service\AdSelectEventExporter;
use DateTime;
use Illuminate\Support\Facades\Log;
use function sprintf;

class AdSelectEventExportCommand extends BaseCommand
{
    protected $signature = 'ops:adselect:event:export';

    protected $description = 'Export events to AdSelect';

    protected $exporterService;

    private $adUser;

    private $eventRepository;

    public function __construct(
        Locker $locker,
        AdSelectEventExporter $exporterService,
        AdUser $adUser,
        NetworkEventRepository $eventRepository
    ) {
        $this->exporterService = $exporterService;
        $this->adUser = $adUser;
        $this->eventRepository = $eventRepository;

        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('[AdSelectEventExport] Command '.$this->signature.' already running.');

            return;
        }

        $this->info('[AdSelectEventExport] Start command '.$this->signature);

        $lastExportDate = Config::fetchDateTime(Config::ADSELECT_EVENT_EXPORT_TIME);

        $this->info(sprintf(
            '[ADSELECT] Trying to export unpaid events from %s',
            $lastExportDate->format(DateTime::ATOM)
        ));

//        TODO SRV-356 fetch NetworkEventLog uuid from adselect
//        $eventUuid =
//        $eventIdFirst = NetworkEventLog::fetchByEventId($eventUuid);
        $eventIdFirst = NetworkEventLog::where('created_at', '>=', $lastExportDate)->min('id');
        $eventIdLast = NetworkEventLog::max('id');

        if (null === $eventIdFirst) {
            $this->info('[ADSELECT] No events to export');

            return;
        }

        $updated = $this->updateNetworkEventsWithAdUserData($eventIdFirst, $eventIdLast);

        $this->info(sprintf(
            '[ADSELECT] Updated %s unpaid events',
            $updated
        ));

        $exported = $this->exporterService->exportUnpaidEvents($eventIdFirst, $eventIdLast);

        $this->info(sprintf(
            '[ADSELECT] Exported %s unpaid events',
            $exported
        ));

        // TODO SRV-356 remove storing last export time and delete key from Config (model and database)
        $dateTime = new DateTime();
        Config::upsertDateTime(Config::ADSELECT_EVENT_EXPORT_TIME, $dateTime);

        $this->info('[AdSelectEventExport] Finished exporting events to AdSelect.');
    }

    private function updateNetworkEventsWithAdUserData(int $eventIdFirst, int $eventIdLast): int
    {
        $limit = NetworkEventRepository::PACKAGE_SIZE;
        $offset = 0;
        $updated = 0;

        do {
            $events =
                NetworkEventLog::whereBetween('id', [$eventIdFirst, $eventIdLast])->whereNull('event_value')->take(
                    $limit
                )->skip($offset)->get();

            foreach ($events as $event) {
                /** @var $event NetworkEventLog */

                if ($event->human_score !== null && $event->our_userdata !== null) {
                    continue;
                }

                try {
                    $event->updateWithUserContext($this->userContext($this->adUser, $event));
                    $event->save();
                    $updated++;
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

            $offset += NetworkEventRepository::PACKAGE_SIZE;
        } while (count($events) === NetworkEventRepository::PACKAGE_SIZE);

        return $updated;
    }

    private function userContext(AdUser $adUser, NetworkEventLog $event): UserContext
    {
        static $userInfoCache = [];

        $impressionContext = ImpressionContext::fromEventData($event->headers, $event->ip, $event->tracking_id);
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
}
