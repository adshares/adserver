<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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
use Adshares\Adserver\Models\User;

use function file_exists;
use function GuzzleHttp\json_encode;
use function str_pad;

use const STR_PAD_LEFT;

class AdsSend extends BaseCommand
{
    protected $signature = 'ads:send {--external}';

    protected $description = 'For testing purposes: sends ads to seeded accounts';

    /** @var array */
    private $data = [];

    public function handle(AdsClient $adsClient): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->signature . ' already running');

            return;
        }

        $filePath = base_path('accounts.local.php');
        if (file_exists($filePath)) {
            $this->data = include $filePath;
        }

        $msg = [];
        $sendFromSelf = true;

        if ($this->option('external')) {
            $sendFromSelf = false;
        }

        $msg[] = $this->send($sendFromSelf ? $adsClient : 'pub', 'pub@dev.dev', random_int(100, 1000));
        $msg[] = $this->send($sendFromSelf ? $adsClient : 'adv', 'adv@dev.dev', random_int(100, 1000));
        $msg[] = $this->send($sendFromSelf ? $adsClient : 'dev', 'dev@dev.dev', random_int(100, 1000));
        $msg[] = $this->send($sendFromSelf ? $adsClient : 'postman', 'postman@dev.dev', random_int(100, 1000));
        $msg[] = $this->send($adsClient, 'test@dev.dev', random_int(100, 500));

        $this->info(json_encode($msg));
    }

    private function send($from, string $to, int $amount): array
    {
        if ($from instanceof AdsClient) {
            $client = $from;
        } else {
            $drv = new CliDriver(
                $this->data[$from]['ADSHARES_ADDRESS'],
                $this->data[$from]['ADSHARES_SECRET'],
                $this->data[$from]['ADSHARES_NODE_HOST'],
                $this->data[$from]['ADSHARES_NODE_PORT']
            );
            $drv->setCommand(config('app.adshares_command'));
            $drv->setWorkingDir(config('app.adshares_workingdir'));
            $client = new AdsClient($drv);
        }

        $UID = User::where('email', $to)->first()->uuid
            ?? (($this->data[$to] ?? false)
                ? $this->data[$to]['uid']
                : null)
            ?? User::where('email', $this->data[$to]['email'])->first()->uuid;

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
