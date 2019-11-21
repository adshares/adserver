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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Tests\Console\TestCase;
use Adshares\Supply\Application\Service\AdSelect;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdSelectInventoryExporterCommandTest extends TestCase
{
    use RefreshDatabase;

    public function testExport(): void
    {
        $this->app->bind(AdSelect::class, function () {
            $adSelect = $this->createMock(AdSelect::class);

            return $adSelect;
        });

        $this->artisan('ops:adselect:inventory:export')
            ->assertExitCode(0);
    }
}
