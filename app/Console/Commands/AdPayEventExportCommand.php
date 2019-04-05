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
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class AdPayEventExportCommand extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ops:adpay:event:export';

    protected $description = 'Exports event data to AdPay';

    public function handle(AdPay $adPay, AdUser $adUser): void
    {
        $this->info('Start command '.$this->signature);

        $eventId = Config::fetchAdPayLastExportedEventId();

        $eventsToExport =
            EventLog::where('id', '>', $eventId)->orderBy('id')->get();

        $this->info('Found '.count($eventsToExport).' events to export.');
        if (count($eventsToExport) > 0) {
            $this->updateEventLogWithAdUserData($adUser, $eventsToExport);

            $events = DemandEventMapper::mapEventCollectionToEventArray($eventsToExport);
            $adPay->addEvents($events);

            $lastExportedEventId = $eventsToExport->last()->id;
            Config::updateAdPayLastExportedEventId($lastExportedEventId);
        }

        $this->info('Finish command '.$this->signature);
    }

    private function updateEventLogWithAdUserData(AdUser $adUser, Collection $eventsToExport): void
    {
        foreach ($eventsToExport as $event) {
            /** @var $event EventLog */
            $userContext = $adUser->getUserContext($event->impressionContext())->toArray();
            $event->human_score = $userContext['human_score'];
            $event->our_userdata = $userContext['keywords'];
            $event->save();
        }
    }
}
