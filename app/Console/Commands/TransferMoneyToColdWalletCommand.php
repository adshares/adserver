<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Demand\Application\Exception\TransferMoneyException;
use Adshares\Demand\Application\Service\TransferMoneyToColdWallet;
use Illuminate\Console\Command;
use function sprintf;

class TransferMoneyToColdWalletCommand extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ops:wallet:transfer:cold';

    protected $description = 'Transfer money from Hot to Cold Wallet when amount is greater than `max` definition';

    /** @var TransferMoneyToColdWallet */
    private $transferMoneyToColdWalletService;

    public function __construct(TransferMoneyToColdWallet $transferMoneyToColdWalletService)
    {
        $this->transferMoneyToColdWalletService = $transferMoneyToColdWalletService;

        parent::__construct();
    }

    public function handle(): void
    {
        $this->info('[Wallet] Start command '.$this->signature);

        if (!Config::isColdWalletActive()) {
            $this->info('[Wallet] Hot wallet feature is disabled.');

            return;
        }

        $waitingPayments = UserLedgerEntry::waitingPayments();

        try {
            $response = $this->transferMoneyToColdWalletService->transfer($waitingPayments);

            if (!$response) {
                $this->info('[Wallet] No clicks amount to transfer between Hot and Cold wallets.');

                return;
            }

            $message = sprintf(
                '[Wallet] Successfully transfer %s clicks to Cold Wallet (%s) (txid: %s).',
                $response->getTransferValue(),
                config('app.adshares_wallet_cold_address'),
                $response->getTransactionId()
            );

            $this->info($message);
        } catch (TransferMoneyException $exception) {
            $this->error($exception->getMessage());
        }
    }
}
