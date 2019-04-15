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
use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\EventLog;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Demand\Application\Service\AdPay;
use Adshares\Supply\Application\Dto\ImpressionContextException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use function sprintf;

class AdPayEventExportCommand extends Command
{
    use LineFormatterTrait;

    private const EVENTS_BUNDLE_MAXIMAL_SIZE = 100;

    protected $signature = 'ops:adpay:event:export';

    protected $description = 'Exports event data to AdPay';

    public function handle(AdPay $adPay, AdUser $adUser): void
    {
        $timeStart = microtime(true);
        $this->info('[AdPayEventExport] Start command '.$this->signature);

        $eventIdFirst = Config::fetchInt(Config::ADPAY_LAST_EXPORTED_EVENT_ID) + 1;

        do {
            $eventsToExport = $this->fetchEventsToExport($eventIdFirst);

            $this->info('[AdPayEventExport] Found '.count($eventsToExport).' events to export.');
            if (count($eventsToExport) > 0) {
                $this->updateEventLogWithAdUserData($adUser, $eventsToExport);

                $events = DemandEventMapper::mapEventCollectionToEventArray($eventsToExport);
                $adPay->addEvents($events);

                $eventIdLastExported = $eventsToExport->last()->id;

                Config::upsertInt(Config::ADPAY_LAST_EXPORTED_EVENT_ID, $eventIdLastExported);
                $eventIdFirst = $eventIdLastExported + 1;
            }
        } while (self::EVENTS_BUNDLE_MAXIMAL_SIZE === count($eventsToExport));

        $this->info('[AdPayEventExport] Finish command '.$this->signature);
        $executionTime = microtime(true) - $timeStart;
        $this->info(sprintf('[AdPayEventExport] Export took %d seconds', (int)$executionTime));
    }

    private function fetchEventsToExport(int $eventIdFirst): Collection
    {
        return EventLog::where('id', '>=', $eventIdFirst)
            ->orderBy('id')
            ->limit(self::EVENTS_BUNDLE_MAXIMAL_SIZE)
            ->get();
    }

    private function updateEventLogWithAdUserData(AdUser $adUser, Collection $eventsToExport): void
    {
        foreach ($eventsToExport as $event) {
            /** @var $event EventLog */

            if (null !== $event->human_score && null !== $event->our_userdata) {
                continue;
            }

            try {
                $userContext = $adUser->getUserContext($event->impressionContext())->toArray();
                $event->human_score = $userContext['human_score'];
                $event->our_userdata = $userContext['keywords'];

                $event->save();
            } catch (ImpressionContextException $e) {
                Log::error(
                    sprintf(
                        '{"command":"%s","event":"%d","error":"%s"}',
                        $this->signature,
                        $event->id,
                        $e->getMessage()
                    )
                );
            }
        }
    }
}
