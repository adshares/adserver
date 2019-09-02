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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Exception\CommandException;
use Adshares\Ads\Util\AdsConverter;
use Adshares\Adserver\Exceptions\ConsoleCommandException;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Utilities\SqlUtils;
use DateTime;
use Illuminate\Database\QueryException;

class AdsGetTxIn extends BaseCommand
{
    public const EXIT_CODE_SUCCESS = 0;

    public const EXIT_CODE_ERROR = 1;

    protected $signature = 'ads:get-tx-in';

    protected $description = 'Searches blockchain log of AdServer for incoming transfers';

    public function handle(AdsClient $adsClient): int
    {
        if (!$this->lock()) {
            $this->info('[AdsGetTxIn] Command '.$this->signature.' already running');

            return self::EXIT_CODE_ERROR;
        }

        $this->info('Start command '.$this->signature);

        try {
            $response = $adsClient->getLog(Config::fetchDateTime(Config::ADS_LOG_START));
        } catch (CommandException $exc) {
            $this->error('Cannot get log');

            return self::EXIT_CODE_ERROR;
        }
        $log = $response->getLog();

        if (count($log) > 0) {
            $txsCount = $this->parse($log);

            try {
                $lastEventDate = (new DateTime())->setTimestamp($this->getLastEventTime($log));

                Config::upsertDateTime(Config::ADS_LOG_START, $lastEventDate);
            } catch (ConsoleCommandException $exc) {
                $this->error('Cannot get time of last event');
            }
        } else {
            $txsCount = 0;
        }
        $this->info("Number of added txs: ${txsCount}");

        return self::EXIT_CODE_SUCCESS;
    }

    private function parse(array $log): int
    {
        $count = 0;
        foreach ($log as $logEntry) {
            $type = $logEntry['type'];
            if (($type === 'send_many' || $type === 'send_one') && $logEntry['inout'] === 'in') {
                $txid = $logEntry['id'];
                $amount = $logEntry['amount'];
                $address = $logEntry['address'];

                $amountInClicks = AdsConverter::adsToClicks($amount);

                $adsTx = new AdsPayment();
                $adsTx->txid = $txid;
                $adsTx->amount = $amountInClicks;
                $adsTx->address = $address;

                try {
                    $adsTx->save();
                    ++$count;
                } catch (QueryException $exc) {
                    if (SqlUtils::isDuplicatedEntry($exc)) {
                        $this->info("Tx [$txid] rejected. It is already in the database.");
                    } else {
                        $excMessage = $exc->getMessage();
                        $this->error("Tx [$txid] rejected due to\n    ${excMessage}");
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Returns time of last event in log.
     *
     * @param array $log log
     *
     * @return int time of last event
     * @throws ConsoleCommandException if log array is empty
     */
    private function getLastEventTime(array $log): int
    {
        $count = count($log);
        $lastEventIndex = $count - 1;
        if ($count > 0) {
            return $log[$lastEventIndex]['time'];
        }
        throw new ConsoleCommandException();
    }
}
