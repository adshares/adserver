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

declare(strict_types=1);

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Jobs\AdsSendOne;
use Adshares\Adserver\Mail\WithdrawalSuccess;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Common\Application\Service\AdsRpcClient;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class WalletWithdrawalCheckCommand extends BaseCommand
{
    protected $signature = 'ops:wallet:withdrawal:check';
    protected $description = 'Check if there is withdrawals to auto send.';
    private ExchangeRateReader $exchangeRateReader;
    private AdsRpcClient $rpcClient;

    public function __construct(Locker $locker, ExchangeRateReader $exchangeRateReader, AdsRpcClient $rpcClient)
    {
        $this->exchangeRateReader = $exchangeRateReader;
        $this->rpcClient = $rpcClient;
        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->signature . ' already running');
            return;
        }
        $this->info('[Wallet] Start command ' . $this->signature);
        $exchangeRate = $this->exchangeRateReader->fetchExchangeRate()->getValue();

        $count = 0;
        $users = User::findByAutoWithdrawal();
        $this->info(sprintf('[Wallet] %d users with auto withdrawal enabled', count($users)));
        foreach ($users as $user) {
            /** @var User $user */
            $balance = $user->getWalletBalance();
            if ($balance * $exchangeRate >= $user->auto_withdrawal_limit) {
                try {
                    $this->withdraw($user, $balance);
                    $this->info(sprintf('[Wallet] A withdrawal has been requested for %s', $user->label));
                    ++$count;
                } catch (RuntimeException $exception) {
                    $this->error($exception->getMessage());
                }
            }
        }
        $this->info(sprintf('[Wallet] %d withdrawals has been requested', $count));
    }

    public function withdraw(User $user, int $amount): void
    {
        $addressFrom = new AccountId(config('app.adshares_address'));

        if (null === $user->wallet_address) {
            throw new RuntimeException(
                sprintf('Cannot withdraw, user #%d does not have an wallet address set', $user->id)
            );
        }

        switch ($user->wallet_address->getNetwork()) {
            case WalletAddress::NETWORK_ADS:
                $addressTo = $user->wallet_address->getAddress();
                $message = '';
                break;
            case WalletAddress::NETWORK_BSC:
                $gateway = $this->rpcClient->getGateway(WalletAddress::NETWORK_BSC);
                $addressTo = $gateway->getAddress();
                $message = $gateway->getPrefix() . preg_replace('/^0x/', '', $user->wallet_address->getAddress());
                break;
            default:
                throw new RuntimeException(
                    sprintf('Cannot withdraw, unsupported network "%s"', $user->wallet_address->getNetwork())
                );
        }

        $balance = $user->getWalletBalance();
        $amount = AdsUtils::calculateAmount((string)$addressFrom, $addressTo, $balance);
        $ledgerEntry = UserLedgerEntry::construct(
            $user->id,
            -$balance,
            UserLedgerEntry::STATUS_PENDING,
            UserLedgerEntry::TYPE_WITHDRAWAL
        )->addressed((string)$addressFrom, $addressTo);
        $ledgerEntry->saveOrFail();

        AdsSendOne::dispatch(
            $ledgerEntry,
            $addressTo,
            $amount,
            $message
        );
        if (null !== $user->email) {
            $fee = AdsUtils::calculateFee((string)$addressFrom, $addressTo, $amount);
            Mail::to($user)->queue(
                new WithdrawalSuccess(
                    $amount,
                    'ADS',
                    $fee,
                    $user->wallet_address
                )
            );
        }
    }
}
