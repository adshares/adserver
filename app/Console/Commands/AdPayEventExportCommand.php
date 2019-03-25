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
use DateTime;
use Illuminate\Console\Command;

class AdPayEventExportCommand extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ops:adpay:event:export';

    protected $description = 'Exports event data to AdPay';

    public function handle(AdPay $adPay, AdUser $adUser): void
    {
        $this->info('Start command '.$this->signature);

        $dateFrom = Config::fetchDateTimeByKey(Config::ADPAY_EVENT_EXPORT_TIME);
        $dateNow = new DateTime();

        $createdEvents = EventLog::where('created_at', '>=', $dateFrom)->get();
        if (count($createdEvents) > 0) {
            foreach ($createdEvents as $event) {
                /** @var $event EventLog */
                $userContext = $adUser->getUserContext($event->impressionContext())->toArray();
                $event->human_score = $userContext['human_score'];
                $event->our_userdata = $userContext['keywords'];
                $event->save();
            }

            $events = DemandEventMapper::mapEventCollectionToEventArray($createdEvents);
            $adPay->addEvents($events);
        }

        Config::updateDateTimeByKey(Config::ADPAY_EVENT_EXPORT_TIME, $dateNow);

        $this->info('Finish command '.$this->signature.' with '.count($createdEvents).' sent events.');
    }
}
