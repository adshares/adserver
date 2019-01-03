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
use Adshares\Ads\Driver\CliDriver;
use Adshares\Ads\Response\TransactionResponse;
use Adshares\Adserver\Models\User;
use Illuminate\Console\Command;
use function str_pad;
use const STR_PAD_LEFT;

class AdsSend extends Command
{
    protected $signature = 'ads:send';

    /** @var array */
    private $data;

    public function handle(): void
    {
        $this->data = include base_path('accounts.local.php');

        $this->info($this->send('pub', 'here', random_int(10, 1000))->getTx()->getId());
        $this->info($this->send('pub2', 'here', random_int(10, 1000))->getTx()->getId());
        $this->info($this->send('adv', 'here', random_int(10, 1000))->getTx()->getId());
        $this->info($this->send('adv2', 'here', random_int(10, 1000))->getTx()->getId());
    }

    private function send(string $from, string $to, int $amount): TransactionResponse
    {
        $drv = new CliDriver(
            $this->data[$from]['ADSHARES_ADDRESS'],
            $this->data[$from]['ADSHARES_SECRET'],
            $this->data[$from]['ADSHARES_NODE_HOST'],
            $this->data[$from]['ADSHARES_NODE_PORT']
        );
        $drv->setCommand(config('app.adshares_command'));
        $drv->setWorkingDir(config('app.adshares_workingdir'));

        $client = new AdsClient($drv);

        $UID = User::where('email', $this->data[$from]['email'])->first()->uuid;

        return $client->runTransaction(
            new SendOneCommand(
                $this->data[$to]['ADSHARES_ADDRESS'],
                $amount * 10 ** 11,
                str_pad($UID, 64, '0', STR_PAD_LEFT)
            )
        //            new SendOneCommand(
        //                '0002-00000007-055A',
        //                $amount * 10 ** 11,
        //                str_pad('9b19e1ba71c244f99b69098e93accfca', 64, '0', STR_PAD_LEFT)
        //            )
        );
    }
}
