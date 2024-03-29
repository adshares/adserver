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

use Adshares\Ads\Util\AdsConverter;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Mail\WalletFundsEmail;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Application\Model\Currency;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Demand\Application\Service\WalletFundsChecker;
use DateTime;
use Illuminate\Support\Facades\Mail;

class WalletAmountCheckCommand extends BaseCommand
{
    private const SEND_EMAIL_MINIMAL_INTERVAL_IN_SECONDS = 1800;

    protected $signature = 'ops:wallet:transfer:check';
    protected $description = 'Check and inform operator about insufficient funds on the account.';

    public function __construct(
        Locker $locker,
        private readonly ExchangeRateReader $exchangeRateReader,
        private readonly WalletFundsChecker $hotWalletCheckerService
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

        $appCurrency = Currency::from(config('app.currency'));
        $exchangeRate = match ($appCurrency) {
            Currency::ADS => ExchangeRate::ONE($appCurrency),
            default => $this->exchangeRateReader->fetchExchangeRate(null, $appCurrency->value),
        };
        $waitingPayments = UserLedgerEntry::waitingPayments();
        $allUsersBalance = UserLedgerEntry::getBalanceForAllUsers();

        $transferValue = $this->hotWalletCheckerService->calculateTransferValue(
            $exchangeRate->toClick($waitingPayments),
            $exchangeRate->toClick($allUsersBalance)
        );

        if (0 === $transferValue) {
            $this->info('[Wallet] No need to transfer clicks from Cold Wallet.');
            return;
        }

        if ($this->shouldEmailBeSent()) {
            $email = config('app.technical_email');
            $transferValueInAds = number_format((float)AdsConverter::clicksToAds($transferValue), 4, '.', '');

            Mail::to($email)->queue(
                new WalletFundsEmail($transferValueInAds, config('app.adshares_address'))
            );

            $message = sprintf(
                '[Wallet] Email has been sent to %s to transfer %s ADS from Cold (%s) to Hot Wallet (%s).',
                $email,
                $transferValueInAds,
                config('app.cold_wallet_address'),
                config('app.adshares_address')
            );
            $this->info($message);

            Config::upsertDateTime(Config::OPERATOR_WALLET_EMAIL_LAST_TIME, new DateTime());

            return;
        }

        $this->info('[Wallet] Email does not need to be sent because we sent it a few minutes before.');
    }

    private function shouldEmailBeSent(): bool
    {
        $date = Config::fetchDateTime(Config::OPERATOR_WALLET_EMAIL_LAST_TIME);

        $interval = sprintf('%d second', self::SEND_EMAIL_MINIMAL_INTERVAL_IN_SECONDS);

        return $date->modify($interval) < new DateTime();
    }
}
