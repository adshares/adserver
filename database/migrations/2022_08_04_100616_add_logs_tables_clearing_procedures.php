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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddLogsTablesClearingProcedures extends Migration
{
    public function up(): void
    {
        $this->dropProcedures();
        DB::statement(self::CLEAN_LOGS_PROCEDURE);
        DB::statement(self::CLEAN_NETWORK_LOGS_PROCEDURE);
    }

    public function down(): void
    {
        $this->dropProcedures();
    }

    private const CLEAN_LOGS_PROCEDURE_NAME = 'clean_logs';
    private const CLEAN_NETWORK_LOGS_PROCEDURE_NAME = 'clean_network_logs';
    private const CLEAN_LOGS_PROCEDURE = <<<XXXX
CREATE PROCEDURE `clean_logs`(
	IN `_date` DATETIME
)
LANGUAGE SQL
NOT DETERMINISTIC
CONTAINS SQL
SQL SECURITY DEFINER
COMMENT ''
BEGIN
    SET @last := (SELECT MIN(id) FROM event_logs WHERE created_at = _date);
    IF @last IS NOT NULL THEN
        label: LOOP
            SET @offset := (SELECT MIN(id) FROM event_logs);
            DELETE FROM event_logs WHERE id BETWEEN @offset AND @offset+999 AND id < @last ORDER BY id ASC;
            IF @offset >= @last THEN
                LEAVE label;
            END IF;
        END LOOP label;
    END IF;
END
XXXX;

    private const CLEAN_NETWORK_LOGS_PROCEDURE = <<<XXXX
CREATE PROCEDURE `clean_network_logs`(
	IN `_date` DATETIME
)
LANGUAGE SQL
NOT DETERMINISTIC
CONTAINS SQL
SQL SECURITY DEFINER
COMMENT ''
BEGIN
    SET @last := (SELECT MIN(id) FROM network_impressions WHERE created_at = _date);
    IF @last IS NOT NULL THEN
        label: LOOP
            SET @offset := (SELECT MIN(id) FROM network_impressions);
            DELETE FROM network_impressions WHERE id BETWEEN @offset AND @offset+999 AND id < @last ORDER BY id ASC;
            IF @offset >= @last THEN
                LEAVE label;
            END IF;
        END LOOP label;
    END IF;
    
    SET @last := (SELECT MIN(id) FROM network_cases WHERE created_at = _date);
    IF @last IS NOT NULL THEN
        label: LOOP
            SET @offset := (SELECT MIN(id) FROM network_cases);
            DELETE FROM network_cases WHERE id BETWEEN @offset AND @offset+999 AND id < @last ORDER BY id ASC;
            IF @offset >= @last THEN
                LEAVE label;
            END IF;
        END LOOP label;
    END IF;
    
    SET @last := (SELECT MIN(id) FROM network_case_clicks WHERE created_at = _date);
    IF @last IS NOT NULL THEN
        label: LOOP
            SET @offset := (SELECT MIN(id) FROM network_case_clicks);
            DELETE FROM network_case_clicks WHERE id BETWEEN @offset AND @offset+999 AND id < @last ORDER BY id ASC;
            IF @offset >= @last THEN
                LEAVE label;
            END IF;
        END LOOP label;
    END IF;
    
    SET @last := (SELECT MIN(id) FROM network_case_payments WHERE created_at = _date);
    IF @last IS NOT NULL THEN
        label: LOOP
            SET @offset := (SELECT MIN(id) FROM network_case_payments);
            DELETE FROM network_case_payments WHERE id BETWEEN @offset AND @offset+999 AND id < @last ORDER BY id ASC;
            IF @offset >= @last THEN
                LEAVE label;
            END IF;
        END LOOP label;
    END IF;
END
XXXX;

    private function dropProcedures(): void
    {
        foreach ([self::CLEAN_LOGS_PROCEDURE_NAME, self::CLEAN_NETWORK_LOGS_PROCEDURE_NAME] as $procedureName) {
            DB::statement(sprintf('DROP PROCEDURE IF EXISTS %s;', $procedureName));
        }
    }
}
