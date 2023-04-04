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

use Adshares\Supply\Domain\ValueObject\HostStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $allowedStatuses = join(
            ',',
            array_map(
                fn($status) => "'" . $status->value . "'",
                HostStatus::cases()
            ),
        );
        DB::statement(
            sprintf(
                'ALTER TABLE `network_hosts` CHANGE `status` `status` ENUM(%s) NOT NULL DEFAULT %s',
                $allowedStatuses,
                "'" . HostStatus::Initialization->value . "'",
            )
        );
    }

    public function down(): void
    {
        $allowedStatuses = join(
            ',',
            array_map(
                fn($status) => "'" . $status->value . "'",
                [HostStatus::Operational, HostStatus::Initialization, HostStatus::Failure, HostStatus::Unreachable]
            ),
        );
        DB::statement(
            sprintf(
                'ALTER TABLE `network_hosts` CHANGE `status` `status` ENUM(%s) NOT NULL DEFAULT %s',
                $allowedStatuses,
                "'" . HostStatus::Initialization->value . "'",
            )
        );
    }
};
