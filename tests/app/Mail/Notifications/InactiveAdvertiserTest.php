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

namespace Adshares\Adserver\Tests\Mail\Notifications;

use Adshares\Adserver\Mail\Notifications\InactiveAdvertiser;
use Adshares\Adserver\Tests\Mail\MailTestCase;

class InactiveAdvertiserTest extends MailTestCase
{
    public function testBuild(): void
    {
        $mailable = new InactiveAdvertiser();

        $mailable->assertSeeInText("We noticed you haven't created your first campaign yet.");
    }
}
