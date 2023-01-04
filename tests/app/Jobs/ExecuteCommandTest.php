<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Jobs;

use Adshares\Adserver\Jobs\ExecuteCommand;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ExecuteCommandTest extends TestCase
{
    public function testHandleOK(): void
    {
        Log::spy();
        Artisan::command('ads:test', fn() => 1);
        $job = new ExecuteCommand('ads:test');

        $job->handle();

        Log::shouldHaveReceived('error')->once()->with('Job (command ads:test) failed');
    }
}
