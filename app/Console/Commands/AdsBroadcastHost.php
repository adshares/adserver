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

use Adshares\Ads\AdsClient;
use Adshares\Ads\Command\BroadcastCommand;
use Adshares\Ads\Exception\CommandException;
use Adshares\Adserver\Console\LineFormatterTrait;
use Illuminate\Console\Command;
use function strlen;
use function strtoupper;

class AdsBroadcastHost extends Command
{
    use LineFormatterTrait;

    const BROADCAST_PREFIX = 'AdServer.';

    const EXIT_CODE_SUCCESS = 0;
    const EXIT_CODE_ERROR = 1;

    /**
     * @var string
     */
    protected $signature = 'ads:broadcast-host';

    /**
     * @var string
     */
    protected $description = 'Sends AdServer host address as broadcast message to blockchain';

    /**
     * @var string
     */
    private $infoApiUrl;

    public function __construct()
    {
        parent::__construct();
        $this->infoApiUrl = config('app.adserver_info_url');
    }

    /**
     * @param AdsClient $adsClient
     *
     * @return int
     */
    public function handle(AdsClient $adsClient): int
    {
        $this->info('Start command '.$this->signature);

        $message = $this->strToHex(urlencode(self::BROADCAST_PREFIX.$this->infoApiUrl));

        $command = new BroadcastCommand($message);
        try {
            $response = $adsClient->runTransaction($command);
            $txid = $response->getTx()->getId();
            $this->info("Broadcast message sent successfully. Txid: [$txid]");
        } catch (CommandException $exc) {
            $this->error('Cannot send broadcast due to error '.$exc->getCode());

            return self::EXIT_CODE_ERROR;
        }

        return self::EXIT_CODE_SUCCESS;
    }

    private function strToHex(string $string): string
    {
        $hex = '';
        $length = strlen($string);
        for ($i = 0; $i < $length; $i++) {
            $ord = ord($string[$i]);
            $hexCode = dechex($ord);
            $hex .= substr('0'.$hexCode, -2);
        }

        return strtoupper($hex);
    }
}
