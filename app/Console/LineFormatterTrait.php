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

namespace Adshares\Adserver\Console;

use Illuminate\Support\Facades\Log;

trait LineFormatterTrait
{
    private $logLevelMapper = [
        'info' => 'info',
        'warn' => 'warning',
        'error' => 'error',
        'alert' => 'alert',
        'comment' => 'info',
    ];

    public function line($string, $style = null, $verbosity = null)
    {
        if (in_array($style, $this->logLevelMapper, true)) {
            Log::$style($string);
        }

        $styled = $style ? "<$style>$string</$style>" : $string;
        $this->output->writeln($styled, $this->parseVerbosity($verbosity));
    }
}
