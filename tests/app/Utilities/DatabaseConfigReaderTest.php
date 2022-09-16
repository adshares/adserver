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

namespace Adshares\Adserver\Tests\Utilities;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;

class DatabaseConfigReaderTest extends TestCase
{
    /**
     * @dataProvider smtpPasswordProvider
     */
    public function testDecryptSmtpPassword(string $smtpPassword): void
    {
        Config::updateAdminSettings([Config::MAIL_SMTP_PASSWORD => $smtpPassword]);

        DatabaseConfigReader::overwriteAdministrationConfig();

        self::assertNotEquals($smtpPassword, Config::fetchAdminSettings(true)[Config::MAIL_SMTP_PASSWORD]);
        self::assertEquals($smtpPassword, config('mail.mailers.smtp.password'));
    }

    public function smtpPasswordProvider(): array
    {
        return [
            'empty' => [''],
            'non empty' => ['test-pass'],
        ];
    }
}
