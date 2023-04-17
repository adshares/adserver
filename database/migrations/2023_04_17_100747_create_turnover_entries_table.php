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

use Adshares\Supply\Domain\ValueObject\TurnoverEntryType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('turnover_entries', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->timestamp('hour_timestamp')->index();
            $allowedTypes = array_map(fn($type) => $type->name, TurnoverEntryType::cases());
            $table->enum('type', $allowedTypes)->index();
            $table->bigInteger('amount');
            $table->binary('ads_address')->nullable();
        });
        DB::statement('ALTER TABLE turnover_entries MODIFY ads_address varbinary(6)');
    }

    public function down(): void
    {
        Schema::dropIfExists('turnover_entries');
    }
};
