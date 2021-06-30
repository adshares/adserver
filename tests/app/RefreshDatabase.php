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

namespace Adshares\Adserver\Tests;

use Adshares\Adserver\Facades\DB;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase as BaseTrait;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

trait RefreshDatabase
{
    use BaseTrait {
        BaseTrait::refreshTestDatabase as parentRefreshTestDatabase;
    }

    protected function refreshTestDatabase()
    {
        DB::statement(sprintf("SET time_zone = '%s'", config('app.timezone')));

        if (!RefreshDatabaseState::$migrated) {
            if (config('app.refresh_testing_database')) {
                $this->artisan(
                    'migrate:fresh',
                    $this->shouldDropViews() ? [
                        '--drop-views' => true,
                    ] : []
                );
            } else {
                $this->artisan('migrate');
            }

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }
}
