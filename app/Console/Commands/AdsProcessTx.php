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
     * @var string blockchain address of AdServer
     */
    private $adServerAddress;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->adServerAddress = config('app.adshares_address');
    }

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

        $dbTxs = AdsTxIn::where('status', AdsTxIn::STATUS_NEW)->get();

        foreach ($dbTxs as $dbTx) {
            $this->handleDbTx($adsClient, $dbTx);
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

    /**
     * @param AdsClient $adsClient
     * @param AdsTxIn $dbTx
     */
    private function handleDbTx(AdsClient $adsClient, $dbTx)
    {
        $transaction = $adsClient->getTransaction($dbTx->txid)->getTxn();
        $type = $transaction->getType();
        switch ($type) {
            case 'send_many':
                /** @var $transaction SendManyTransaction */
                $this->handleSendManyTx($dbTx, $transaction);
                break;

            case 'send_one':
                /** @var $transaction SendOneTransaction */
                $this->handleSendOneTx($dbTx, $transaction);
                break;

            default:
                $dbTx->status = AdsTxIn::STATUS_INVALID;
                $dbTx->save();
                break;
        }
    }

    /**
     * @param AdsTxIn $dbTx
     * @param SendManyTransaction $transaction
     */
    private function handleSendManyTx($dbTx, $transaction): void
    {
        $isTxTargetValid = false;
        $wiresCount = $transaction->getWireCount();

        if ($wiresCount > 0) {
            $wires = $transaction->getWires();

            foreach ($wires as $wire) {
                /** @var $wire SendManyTransactionWire */
                $targetAddr = $wire->getTargetAddress();
                if ($targetAddr === $this->adServerAddress) {
                    $isTxTargetValid = true;
                    break;
                }
            }
        }

        $dbTx->status = $isTxTargetValid ? AdsTxIn::STATUS_RESERVED : AdsTxIn::STATUS_INVALID;
        $dbTx->save();
    }

    /**
     * @param AdsTxIn $dbTx
     * @param SendOneTransaction $transaction
     */
    private function handleSendOneTx($dbTx, $transaction): void
    {
        $targetAddr = $transaction->getTargetAddress();

        if ($targetAddr === $this->adServerAddress) {
            $message = $transaction->getMessage();
            $user = User::where('uuid', hex2bin($this->extractUuidFromMessage($message)))->first();

            if (null === $user) {
                $dbTx->status = AdsTxIn::STATUS_RESERVED;
                $dbTx->save();
            } else {
                $senderAddress = $transaction->getSenderAddress();
                $amount = $transaction->getAmount();
                // add to ledger
                $ul = new UserLedger;
                $ul->user_id = $user->id;
                $ul->amount = $amount;
                $ul->address_from = $senderAddress;
                $ul->address_to = $targetAddr;
                $ul->txid = $dbTx->txid;

                $dbTx->status = AdsTxIn::STATUS_USER_DEPOSIT;
                // dbTx added to ledger will not be processed again
                DB::transaction(function () use ($ul, $dbTx) {
                    $ul->save();
                    $dbTx->save();
                });
            }
        } else {
            $dbTx->status = AdsTxIn::STATUS_INVALID;
            $dbTx->save();
        }
    }
}
