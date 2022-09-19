<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

declare(strict_types=1);

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Common\Application\Model\Currency;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Demand\Application\Exception\TransferMoneyException;
use Adshares\Demand\Application\Service\TransferMoneyToColdWallet;

class TransferMoneyToColdWalletCommand extends BaseCommand
{
    protected $signature = 'ops:wallet:transfer:cold';
    protected $description = 'Transfer money from Hot to Cold Wallet when amount is greater than `max` definition';

    public function __construct(
        Locker $locker,
        private readonly ExchangeRateReader $exchangeRateReader,
        private readonly TransferMoneyToColdWallet $transferMoneyToColdWalletService
    ) {
        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->signature . ' already running');
            return;
        }

        $this->info('[Wallet] Start command ' . $this->signature);

        if (!config('app.cold_wallet_is_active')) {
            $this->info('[Wallet] Cold wallet feature is disabled.');
            return;
        }

        $waitingPayments = UserLedgerEntry::waitingPayments();
        $appCurrency = Currency::from(config('app.currency'));
        $waitingPaymentsInClicks = match ($appCurrency) {
            Currency::ADS => $waitingPayments,
            default => $this->exchangeRateReader->fetchExchangeRate(null, $appCurrency->value)
                ->toClick($waitingPayments),
        };

        try {
            $response = $this->transferMoneyToColdWalletService->transfer($waitingPaymentsInClicks);

            if (!$response) {
                $this->info('[Wallet] No clicks amount to transfer between Hot and Cold wallets.');
                return;
            }

            $message = sprintf(
                '[Wallet] Successfully transfer %s clicks to Cold Wallet (%s) (txid: %s).',
                $response->getTransferValue(),
                config('app.cold_wallet_address'),
                $response->getTransactionId()
            );

            $this->info($message);
        } catch (TransferMoneyException $exception) {
            $this->error($exception->getMessage());
        }

        $this->info('[Wallet] Finish command ' . $this->signature);
    }
}
