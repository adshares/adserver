<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Ads;
use Adshares\Ads\AdsClient;
use Adshares\Adserver\Console\LineFormatterTrait;
use DateTime;
use Illuminate\Console\Command;
use function sprintf;
use const PHP_EOL;

class AdsMe extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ads:me';

    public function handle(AdsClient $adsClient)
    {
        $this->info('Start command '.$this->signature);
        $me = $adsClient->getMe();
        $this->info(Ads\Util\AdsConverter::clicksToAds($me->getAccount()->getBalance()));

        $log = $adsClient->getLog(new DateTime('@0'));

        foreach ($log->getLog() as $entry) {
            echo sprintf(
                '%s'.PHP_EOL,
                $entry['type']
            );
        }
    }
}
