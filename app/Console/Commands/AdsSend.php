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

use Adshares\Ads\AdsClient;
use Adshares\Ads\Command\SendOneCommand;
use Illuminate\Console\Command;

class AdsSend extends Command
{
    protected $signature = 'ads:send';

    public function handle(AdsClient $adsClient)
    {
        $response = $adsClient->runTransaction(new SendOneCommand(
            config('app.adshares_address'),
            10 * pow(10, 11), '0000000000000000000000000000000028a9dbfdb3244297b0e1bb66fc0dceb8'));
        $this->info($response->getTx()->getId());
    }
}
