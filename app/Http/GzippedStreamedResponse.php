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

namespace Adshares\Adserver\Http;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @parent
 */
class GzippedStreamedResponse extends StreamedResponse
{
    public function sendContent(): static
    {
        ob_start(
            function ($output) {
                header('Content-Length: ' . strlen($output));

                return false;
            }
        );
        if (function_exists('ob_gzhandler')) {
            ob_start("ob_gzhandler");
        }
        parent::sendContent();

        ob_end_flush();
        if (function_exists('ob_gzhandler')) {
            ob_end_flush();
        }

        return $this;
    }
}
