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

namespace Adshares\Adserver\Events;

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Utilities\UuidStringGenerator;
use Exception;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversionDefinitionCreating
{
    use Dispatchable, SerializesModels;

    public function __construct(ConversionDefinition $conversionDefinition)
    {
        $conversionDefinition->uuid = UuidStringGenerator::v4();
        $conversionDefinition->is_repeatable = $conversionDefinition->isAdvanced();
        $conversionDefinition->secret = $this->generateSecret();
    }

    private function generateSecret(): string
    {
        try {
            $bytes = random_bytes(16);
        } catch(Exception $exception) {
            $bytes = hex2bin(UuidStringGenerator::v4());
        }

        return Utils::urlSafeBase64Encode($bytes);
    }
}
