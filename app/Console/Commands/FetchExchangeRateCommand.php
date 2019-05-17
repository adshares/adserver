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
use Adshares\Adserver\Repository\Common\EloquentExchangeRateRepository;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Symfony\Component\Console\Command\LockableTrait;

class FetchExchangeRateCommand extends Command
{
    use LineFormatterTrait;
    use LockableTrait;

    private const SQL_ERROR_INTEGRITY_CONSTRAINT_VIOLATION = 23000;

    private const SQL_ERROR_CODE_DUPLICATE_ENTRY = 1062;

    protected $signature = 'ops:exchange-rate:fetch';

    protected $description = 'Fetch exchange rate';

    /** @var EloquentExchangeRateRepository */
    private $repositoryStorable;

    /** @var ExchangeRateRepository */
    private $repositoryRemote;

    public function __construct(
        EloquentExchangeRateRepository $repositoryStorable,
        ExchangeRateRepository $repositoryRemote
    ) {
        $this->repositoryStorable = $repositoryStorable;
        $this->repositoryRemote = $repositoryRemote;

        parent::__construct();
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('[FetchExchangeRate] Command '.$this->signature.' already running.');

            return;
        }

        $this->info('Start command '.$this->signature);

        $exchangeRate = $this->repositoryRemote->fetchExchangeRate();
        $this->info(sprintf('Exchange rate: %s', $exchangeRate->toString()));

        try {
            $this->repositoryStorable->storeExchangeRate($exchangeRate);
        } catch (QueryException $queryException) {
            if (self::SQL_ERROR_INTEGRITY_CONSTRAINT_VIOLATION === (int)$queryException->errorInfo[0]
                && self::SQL_ERROR_CODE_DUPLICATE_ENTRY === (int)$queryException->errorInfo[1]) {
                $this->warn('Exchange rate is already in database');

                return;
            }

            throw $queryException;
        }

        $this->info('Exchange rate has been fetched and stored');
    }
}
