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

namespace Adshares\Adserver\Tests\Listener;

use Adshares\Adserver\Events\ServerEvent;
use Adshares\Adserver\Listeners\ServerEventListener;
use Adshares\Adserver\Models\ServerEventLog;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\ServerEventType;

class ServerEventListenerTest extends TestCase
{
    public function testHandle(): void
    {
        $event = new ServerEvent(ServerEventType::InventorySynchronized, ['test' => 'OK']);

        (new ServerEventListener())->handle($event);

        self::assertDatabaseHas(ServerEventLog::class, [
            'type' => ServerEventType::InventorySynchronized,
        ]);
        self::assertEquals(['test' => 'OK'], ServerEventLog::first()->properties);
    }
}
