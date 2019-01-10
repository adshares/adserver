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
use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Models\User;
use Illuminate\Console\Command;
use function GuzzleHttp\json_encode;
use function str_pad;
use const STR_PAD_LEFT;

class AdsSend extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ads:send';

    /** @var array */
    private $data;

    public function handle(): void
    {
        $this->data = include base_path('accounts.local.php');

        $msg = [];
        $msg[] = $this->send('pub', 'pub', random_int(1, 10));
        $msg[] = $this->send('adv', 'adv', random_int(1, 10));
        $msg[] = $this->send('dev', 'dev', random_int(100, 1000));
        $msg[] = $this->send('postman', 'postman', random_int(10, 100));

        $this->info(json_encode($msg));
    }

    private function send(string $from, string $to, int $amount): array
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

        $UID = $this->data[$to]['uid'] ?? User::where('email', $this->data[$to]['email'])->first()->uuid;

        if (!$UID) {
            return ["Receiver ($to) not found."];
        }

        return [
            $client->runTransaction(
                new SendOneCommand(
                    config('app.adshares_address'),
                    $amount * 10 ** 11,
                    str_pad($UID, 64, '0', STR_PAD_LEFT)
                )
            )->getTx()->getId(),
            $UID,
        ];
    }
}
