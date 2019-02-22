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
declare(strict_types = 1);

namespace Adshares\Adserver\Console\Commands;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Command\SendOneCommand;
use Adshares\Ads\Driver\CommandError;
use Adshares\Ads\Exception\CommandException;
use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\NetworkEventLog;
use Adshares\Adserver\Models\NetworkPayment;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Exception;
use Illuminate\Console\Command;
use RuntimeException;

class SupplySendPayments extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ops:supply:payments:send';

    public function handle(AdsClient $adsClient): void
    {
        $this->info('Start command '.$this->signature);

        $payments = NetworkPayment::fetchNotProcessed();

        $this->info('Payments to process: '.count($payments));

        foreach ($payments as $payment) {
            try {
                $receiverAddress = $payment->receiver_address;
                $amount = $payment->amount;

                DB::beginTransaction();
                if ($amount > 0) {
                    $command = new SendOneCommand($receiverAddress, $amount);
                    $response = $adsClient->runTransaction($command);

                    $payment->tx_id = $response->getTx()->getId();
                    $payment->tx_time = $response->getTx()->getTime()->getTimestamp();
                    $payment->processed = '1';
                    $payment->save();
                }

                if ($payment->ads_payment_id !== null) {
                    $this->addEntryToUserLedgerWithAdIncome($payment->ads_payment_id, $payment->id);
                }

                DB::commit();
            } catch (CommandException $exception) {
                if ($exception->getCode() === CommandError::LOW_BALANCE) {
                    DB::rollBack();

                    $message = '[Supply] (SupplySendPayments) Insufficient funds on Operator Account. ';
                    $message.= 'Could not send a license fee to %s. Payment (Network) id %s. Amount %s.';

                    $this->info(sprintf($message, $payment->receiver_address, $payment->id, $payment->amount));

                    continue;
                }

                throw new $exception;
            } catch (Exception $exception) {
                DB::rollBack();

                throw $exception;
//                $message = '[Supply] (SupplySendPayments) Unexpected Error (%s).';
//                $message.= 'Payment (Network) id %s. Amount %s.';
//
//                $this->error(sprintf($message, $exception->getMessage(), $payment->id, $payment->amount));

//                continue;
            }
        }

        $this->info('Finished sending Supply payments');
    }

    private function addEntryToUserLedgerWithAdIncome(int $adsPaymentId, int $networkPaymentId): void
    {
        $adServerAddress = config('app.adshares_address');

        $adsPayment = AdsPayment::find($adsPaymentId);
        if ($adsPayment === null) {
            $message = '[Supply] (SupplySendPayments) Problem in SupplySendPayments command. ';
            $message .= 'Invalid ADS_PAYMENT_ID %s for payment %s.';

            throw new RuntimeException(sprintf($message, $adsPaymentId, $networkPaymentId));
        }

        $adsPaymentSenderAddress = $adsPayment->address;
        $adsPaymentTxId = $adsPayment->txid;

        $splitPayments = NetworkEventLog::fetchPaymentsForPublishersByAdsPaymentId($adsPaymentId);
        foreach ($splitPayments as $splitPayment) {
            $userUuid = $splitPayment->publisher_id;
            $amount = $splitPayment->paid_amount;

            $user = User::fetchByUuid($userUuid);

            if ($user === null) {
                $message = '[Supply] (SupplySendPayments) User %s does not exist.';
                throw new RuntimeException(sprintf($message, $userUuid));
            }

            $userLedgerEntry = UserLedgerEntry::constructWithAddressAndTransaction(
                $user->id,
                $amount,
                UserLedgerEntry::STATUS_ACCEPTED,
                UserLedgerEntry::TYPE_AD_INCOME,
                $adsPaymentSenderAddress,
                (string)$adServerAddress,
                $adsPaymentTxId
            );

            $userLedgerEntry->save();
        }
    }
}
