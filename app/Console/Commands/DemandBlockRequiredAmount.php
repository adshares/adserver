<?php declare(strict_types = 1);
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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class DemandBlockRequiredAmount extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ops:demand:payments:block';

    /** @var ExchangeRateReader */
    private $exchangeRateReader;

    public function __construct(ExchangeRateReader $exchangeRateReader)
    {
        $this->exchangeRateReader = $exchangeRateReader;

        parent::__construct();
    }

    public function handle(): void
    {
        $this->info('Start command '.$this->signature);

        $exchangeRate = $this->exchangeRateReader->fetchExchangeRate();

        DB::beginTransaction();

        UserLedgerEntry::pushBlockedToProcessing();

        $blockade = Campaign::fetchRequiredBudgetsPerUser();
        $this->info('Attempt to create '.count($blockade).' blockades.');
        $this->blockAmountOrSuspendCampaigns($blockade, $exchangeRate);

        DB::commit();

        $this->info('Created '.count($blockade).' new blocking Ledger entries.');
    }

    private function blockAmountOrSuspendCampaigns(Collection $blockade, ExchangeRate $exchangeRate): void
    {
        $blockade->each(function ($sum, $userId) use ($exchangeRate) {
            $amount = $exchangeRate->toClick((int)$sum);
            try {
                UserLedgerEntry::blockAdExpense((int)$userId, $amount);
            } catch (InvalidArgumentException $e) {
                Log::warning($e->getMessage());

                Campaign::suspendAllForUserId($userId);
            }
        });
    }
}
