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

use Adshares\Adserver\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class DeleteOverwrittenClassification extends Migration
{
    private const DELETE_STATEMENT = <<<SQL
DELETE
FROM classifications
WHERE id IN (
  SELECT id
  FROM (
         SELECT c.id
         FROM classifications AS c
                INNER JOIN
                (SELECT user_id, banner_id FROM classifications WHERE site_id IS NULL AND status = 0) AS rejected
                ON c.user_id = rejected.user_id AND c.banner_id = rejected.banner_id
         WHERE c.site_id IS NOT NULL
       ) AS ids
)
SQL;

    public function up(): void
    {
        DB::statement(self::DELETE_STATEMENT);
    }

    public function down(): void
    {
    }
}
