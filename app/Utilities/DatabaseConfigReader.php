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

namespace Adshares\Adserver\Utilities;

use Adshares\Adserver\Models\Config;
use Illuminate\Support\Facades\Config as SystemConfig;
use Illuminate\Support\Facades\Crypt;

class DatabaseConfigReader
{
    private const MAIL_KEY_MAP = [
        Config::MAIL_FROM_ADDRESS => 'mail.from.address',
        Config::MAIL_FROM_NAME => 'mail.from.name',
        Config::MAIL_MAILER => 'mail.default',
        Config::MAIL_SMTP_ENCRYPTION => 'mail.mailers.smtp.encryption',
        Config::MAIL_SMTP_HOST => 'mail.mailers.smtp.host',
        Config::MAIL_SMTP_PASSWORD => 'mail.mailers.smtp.password',
        Config::MAIL_SMTP_PORT => 'mail.mailers.smtp.port',
        Config::MAIL_SMTP_USERNAME => 'mail.mailers.smtp.username',
    ];

    public static function overwriteAdministrationConfig(): void
    {
        $settings = Config::fetchAdminSettings(true);
        foreach ($settings as $key => $value) {
            if (Config::MAIL_SMTP_PASSWORD === $key && !empty($value)) {
                $value = Crypt::decryptString($value);
            }
            SystemConfig::set(self::mapKey($key), $value);
        }
    }

    private static function mapKey(string $key): string
    {
        if (isset(self::MAIL_KEY_MAP[$key])) {
            return self::MAIL_KEY_MAP[$key];
        }
        return 'app.' . str_replace('-', '_', $key);
    }
}
