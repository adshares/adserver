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
use Adshares\Adserver\Repository\Common\EloquentExchangeRateRepository;
use Adshares\Adserver\Utilities\SqlUtils;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Illuminate\Database\QueryException;

class FetchExchangeRateCommand extends BaseCommand
{
    protected $signature = 'ops:exchange-rate:fetch';

    protected $description = 'Fetch exchange rate';

    /** @var EloquentExchangeRateRepository */
    private $repositoryStorable;

    /** @var ExchangeRateRepository */
    private $repositoryRemote;

    public function __construct(
        Locker $locker,
        EloquentExchangeRateRepository $repositoryStorable,
        ExchangeRateRepository $repositoryRemote
    ) {
        $this->repositoryStorable = $repositoryStorable;
        $this->repositoryRemote = $repositoryRemote;

        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->signature . ' already running');

            return;
        }

        $this->info('Start command ' . $this->signature);
        $currencies = config('app.exchange_currencies');
        if (empty($currencies)) {
            $this->warn('Exchange currencies list is empty');

            return;
        }

        foreach ($currencies as $currency) {
            $this->info(sprintf('Fetching %s rate', $currency));
            $exchangeRate = $this->repositoryRemote->fetchExchangeRate(null, $currency);
            $this->info(sprintf('Exchange rate: %s', $currency, $exchangeRate->toString()));

            try {
                $this->repositoryStorable->storeExchangeRate($exchangeRate);
            } catch (QueryException $queryException) {
                if (SqlUtils::isDuplicatedEntry($queryException)) {
                    $this->warn('Exchange rate is already in database');
                    continue;
                }
                throw $queryException;
            }
        }

        $this->info('Exchange rates has been fetched and stored');
    }
}
