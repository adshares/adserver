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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Symfony\Component\Console\Command\Command;

class UpsertConfigurationCommand extends BaseCommand
{
    private const COMMAND_SIGNATURE = 'config:upsert';

    protected $signature = self::COMMAND_SIGNATURE . ' {key} {value?}';
    protected $description = 'Updates or inserts configuration entry';

    public function handle(): int
    {
        if (!$this->lock()) {
            $this->info(sprintf('Command %s already running', self::COMMAND_SIGNATURE));
            return Command::FAILURE;
        }

        $key = $this->argument('key');
        $value = null !== $this->argument('value')
            ? $this->argument('value')
            : $this->secret(sprintf('Set value of %s', $key));

        Config::updateAdminSettings([$key => $value]);
        DatabaseConfigReader::overwriteAdministrationConfig();

        return Command::SUCCESS;
    }
}
