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
use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Exceptions\ConsoleCommandException;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\NetworkEventLog;
use Adshares\Adserver\Models\NetworkPayment;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Exception;
use Illuminate\Console\Command;

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
                if ($payment->ads_payment_id !== null) {
                    $this->updateUserLedgerWithAdIncome($payment->ads_payment_id);
                }

                $command = new SendOneCommand($receiverAddress, $amount);
                $response = $adsClient->runTransaction($command);
                $payment->tx_id = $response->getTx()->getId();
                $payment->tx_time = $response->getTx()->getTime()->getTimestamp();
                $payment->processed = '1';
                $payment->save();

                DB::commit();
            } catch (Exception $exception) {
                DB::rollBack();
                $this->error($exception->getMessage());
            }
        }

        $this->info('Finished sending Supply payments');
    }

    private function updateUserLedgerWithAdIncome(int $adsPaymentId): void
    {
        $adServerAddress = config('app.adshares_address');

        $adsPayment = AdsPayment::find($adsPaymentId);
        if ($adsPayment === null) {
            // TODO log null $adsPayment - it means that in Supply DB is NetworkPayment with incorrect ads_payment_id
            throw new ConsoleCommandException(sprintf('Missing ads_payment with id=%d.', $adsPaymentId));
        }
        $adsPaymentSenderAddress = $adsPayment->address;
        $adsPaymentTxId = $adsPayment->txid;

        $splitPayments = NetworkEventLog::fetchPaymentsForPublishersByAdsPaymentId($adsPaymentId);
        foreach ($splitPayments as $splitPayment) {
            $userUuid = $splitPayment->publisher_id;
            $amount = $splitPayment->paid_amount;

            $user = User::fetchByUuid($userUuid);

            if ($user === null) {
                // TODO log null $user - it means that in Supply DB is event with incorrect publisher_id
                continue;
            }

            $ul = new UserLedgerEntry();
            $ul->user_id = $user->id;
            $ul->amount = $amount;
            $ul->address_from = $adsPaymentSenderAddress;
            $ul->address_to = $adServerAddress;
            $ul->status = UserLedgerEntry::STATUS_ACCEPTED;
            $ul->type = UserLedgerEntry::TYPE_AD_INCOME;
            $ul->txid = $adsPaymentTxId;
            $ul->save();
        }
    }
}
