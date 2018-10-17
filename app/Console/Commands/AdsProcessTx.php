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
use Adshares\Ads\Driver\CommandError;
use Adshares\Ads\Entity\Transaction\SendManyTransaction;
use Adshares\Ads\Entity\Transaction\SendManyTransactionWire;
use Adshares\Ads\Entity\Transaction\SendOneTransaction;
use Adshares\Ads\Exception\CommandException;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\AdsTxIn;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedger;
use Illuminate\Console\Command;

class AdsProcessTx extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ads:process-tx';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes incoming txs';

    /**
     * Command ended without error
     */
    const EXIT_CODE_SUCCESS = 0;

    /**
     * Command ended prematurely because block ids could not be updated
     */
    const EXIT_CODE_CANNOT_GET_BLOCK_IDS = 1;

    /**
     * Execute the console command.
     * @param AdsClient $adsClient
     * @return int
     */
    public function handle(AdsClient $adsClient)
    {
        try {
            $this->updateBlockIds($adsClient);
        } catch (CommandException $exc) {
            $code = $exc->getCode();
            $message = $exc->getMessage();
            $this->error("Cannot update blocks due to CommandException:\n"
                . "Code:\n  ${code}\n"
                . "Message:\n  ${message}\n");
            return self::EXIT_CODE_CANNOT_GET_BLOCK_IDS;
        }

        $adServerAddress = config('app.adshares_address');
        $txs = AdsTxIn::where('status', AdsTxIn::STATUS_NEW)->get();
        /** @var $tx AdsTxIn */
        foreach ($txs as $tx) {
            $transaction = $adsClient->getTransaction($tx->txid)->getTxn();
            $type = $transaction->getType();
            switch ($type) {
                case 'send_many':
                    $isTxTargetValid = false;
                    /** @var $sendManyTx SendManyTransaction */
                    $sendManyTx = $transaction;
                    $wiresCount = $sendManyTx->getWireCount();
                    if ($wiresCount > 0) {
                        $wires = $sendManyTx->getWires();
                        foreach ($wires as $wire) {
                            /** @var $wire SendManyTransactionWire */
                            $targetAddr = $wire->getTargetAddress();
                            if ($targetAddr === $adServerAddress) {
                                $isTxTargetValid = true;
                                break;
                            }
                        }
                    }
                    $tx->status = $isTxTargetValid ? AdsTxIn::STATUS_RESERVED : AdsTxIn::STATUS_INVALID;
                    $tx->save();
                    break;

                case 'send_one':
                    /** @var $sendOneTx SendOneTransaction */
                    $sendOneTx = $transaction;
                    $targetAddr = $sendOneTx->getTargetAddress();
                    if ($targetAddr === $adServerAddress) {
                        $message = $sendOneTx->getMessage();
                        $user = User::where('uuid', hex2bin($this->extractUuidFromMessage($message)))->first();
                        if (null === $user) {
                            $tx->status = AdsTxIn::STATUS_RESERVED;
                            $tx->save();
                        } else {
                            $amount = $sendOneTx->getAmount();
                            // add to ledger
                            $ul = new UserLedger;
                            $ul->users_id = $user->id;
                            $ul->amount = $amount;
                            $ul->desc = $tx->txid;

                            $tx->status = AdsTxIn::STATUS_USER_DEPOSIT;
                            // tx added to ledger will not be processed again
                            DB::transaction(function () use ($ul, $tx) {
                                $ul->save();
                                $tx->save();
                            });
                        }
                        break;
                    } else {
                        $tx->status = AdsTxIn::STATUS_INVALID;
                        $tx->save();
                    }
                    break;

                default:
                    $tx->status = AdsTxIn::STATUS_INVALID;
                    $tx->save();
                    break;
            }
        }
        return self::EXIT_CODE_SUCCESS;
    }

    /**
     * Extracts uuid from tx message.
     * @param string $message tx message
     * @return string uuid as hex string
     */
    private function extractUuidFromMessage(string $message): string
    {
        return substr($message, -32);
    }

    /**
     * Updates block data.
     * @param AdsClient $adsClient
     * @throws CommandException
     */
    private function updateBlockIds(AdsClient $adsClient)
    {
        $attempt = 0;
        $attemptMax = 5;
        while (true) {
            try {
                $response = $adsClient->getBlockIds();
                $updatedBlocks = $response->getUpdatedBlocks();
                $this->info("Updated blocks: ${updatedBlocks}");
                if ($updatedBlocks === 0) {
                    break;
                }
            } catch (CommandException $exc) {
                if (CommandError::GET_SIGNATURE_UNAVAILABLE === $exc->getCode()
                    && ++$attempt < $attemptMax) {
                    // try again after 3 seconds sleep
                    sleep(3);
                } else {
                    throw $exc;
                }
            }
        }
    }
}
