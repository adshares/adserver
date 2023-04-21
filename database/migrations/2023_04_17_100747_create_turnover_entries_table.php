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
    private const SQL_DSP_ADVERTISERS_EXPENSE = <<<SQL
INSERT INTO turnover_entries (hour_timestamp, amount, created_at, updated_at, type)
SELECT
    CONCAT(DATE(created_at), ' ', LPAD(HOUR(created_at), 2, '0'), ':00:00') AS hour_timestamp,
    -SUM(amount) AS amount,
    NOW() AS created_at,
    NOW() AS updated_at,
    'DspAdvertisersExpense' AS type
FROM user_ledger_entries
WHERE created_at >= '2023-01-01'
  AND status = 0
  AND type IN (4, 6, 9)
GROUP BY 1;
SQL;

    private const SQL_DSP_EXPENSE = <<<SQL
INSERT INTO turnover_entries (hour_timestamp, ads_address, amount, created_at, updated_at, type)
SELECT
    CONCAT(DATE(created_at), ' ', LPAD(HOUR(created_at), 2, '0'), ':00:00') AS hour_timestamp,
    account_address AS ads_address,
    SUM(fee) AS amount,
    NOW() AS created_at,
    NOW() AS updated_at,
    'DspExpense' AS type
FROM payments
WHERE created_at >= '2023-01-01'
  AND state='sent'
  AND fee > 0
  AND account_address IS NOT NULL
  AND account_address != 0x000100000024
GROUP BY 1, 2;
SQL;

    private const SQL_DSP_BURNT = <<<SQL
INSERT INTO turnover_entries (hour_timestamp, ads_address, amount, created_at, updated_at, type)
SELECT
    CONCAT(DATE(created_at), ' ', LPAD(HOUR(created_at), 2, '0'), ':00:00') AS hour_timestamp,
    account_address AS ads_address,
    SUM(fee) * ? AS amount,
    NOW() AS created_at,
    NOW() AS updated_at,
    ? AS type
FROM payments
WHERE created_at >= '2023-01-01'
  AND state='sent'
  AND fee > 0
  AND account_address = 0x000100000024
GROUP BY 1, 2;
SQL;

    private const SQL_DSP_OPERATOR_FEE = <<<SQL
INSERT INTO turnover_entries (hour_timestamp, amount, created_at, updated_at, type)
SELECT
    i.hour_timestamp as hour_timestamp,
    i.amount - IFNULL(o.amount, 0) AS amount,
    NOW() AS created_at,
    NOW() AS updated_at,
    'DspOperatorFee' AS type
FROM (
    SELECT hour_timestamp, SUM(amount) AS amount
    FROM turnover_entries
    WHERE type = 'DspAdvertisersExpense'
    GROUP BY 1
) i
LEFT JOIN (
    SELECT hour_timestamp, SUM(amount) AS amount
    FROM turnover_entries
    WHERE type IN ('DspLicenseFee', 'DspCommunityFee', 'DspExpense')
    GROUP BY 1
) o
    ON i.hour_timestamp = o.hour_timestamp;
SQL;

    private const SQL_SSP_INCOME = <<<SQL
INSERT INTO turnover_entries (hour_timestamp, ads_address, amount, created_at, updated_at, type)
SELECT
    CONCAT(DATE(created_at), ' ', LPAD(HOUR(created_at), 2, '0'), ':00:00') AS hour_timestamp,
    UNHEX(REPLACE(LEFT(address, 13), '-', '')) AS ads_address,
    SUM(amount) AS amount,
    NOW() AS created_at,
    NOW() AS updated_at,
    'SspIncome' AS type
FROM ads_payments
WHERE status = 2
  AND created_at >= '2023-01-01'
  AND amount > 0
GROUP BY 1, 2;
SQL;

    private const SQL_SSP_LICENSE_FEE = <<<SQL
INSERT INTO turnover_entries (hour_timestamp, ads_address, amount, created_at, updated_at, type)
SELECT
    CONCAT(DATE(a.created_at), ' ', LPAD(HOUR(a.created_at), 2, '0'), ':00:00') AS hour_timestamp,
    receiver_address AS ads_address,
    SUM(n.amount) AS amount,
    NOW() AS created_at,
    NOW() AS updated_at,
    'SspLicenseFee' AS type
FROM network_payments n
    JOIN ads_payments a on a.id = n.ads_payment_id
WHERE a.status = 2
  AND a.created_at >= '2023-01-01'
  AND n.processed = 1
  AND n.amount > 0
GROUP BY 1, 2;
SQL;

    private const SQL_SSP_PUBLISHERS_INCOME = <<<SQL
INSERT INTO turnover_entries (hour_timestamp, amount, created_at, updated_at, type)
SELECT
    CONCAT(DATE(a.created_at), ' ', LPAD(HOUR(a.created_at), 2, '0'), ':00:00') AS hour_timestamp,
    SUM(u.amount) AS amount,
    NOW() AS created_at,
    NOW() AS updated_at,
    'SspPublishersIncome' AS type
FROM user_ledger_entries u
    JOIN ads_payments a ON a.txid = u.txid 
WHERE a.status = 2
  AND a.created_at >= '2023-01-01'
  AND u.status = 0
  AND u.type = 3
  AND u.amount > 0
GROUP BY 1;
SQL;

    private const SQL_SSP_OPERATOR_FEE = <<<SQL
INSERT INTO turnover_entries (hour_timestamp, amount, created_at, updated_at, type)
SELECT
    i.hour_timestamp as hour_timestamp,
    i.amount - IFNULL(o.amount, 0) AS amount,
    NOW() AS created_at,
    NOW() AS updated_at,
    'SspOperatorFee' AS type
FROM (
    SELECT hour_timestamp, SUM(amount) AS amount
    FROM turnover_entries
    WHERE type = 'SspIncome'
    GROUP BY 1
) i
LEFT JOIN (
    SELECT hour_timestamp, SUM(amount) AS amount
    FROM turnover_entries
    WHERE type IN ('SspLicenseFee', 'SspPublishersIncome')
    GROUP BY 1
) o
    ON i.hour_timestamp = o.hour_timestamp;
SQL;

    public function up(): void
    {
        Schema::create('turnover_entries', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->timestamp('hour_timestamp')->index();
            $allowedTypes = [
                TurnoverEntryType::DspAdvertisersExpense->name,
                TurnoverEntryType::DspLicenseFee->name,
                TurnoverEntryType::DspOperatorFee->name,
                TurnoverEntryType::DspCommunityFee->name,
                TurnoverEntryType::DspExpense->name,
                TurnoverEntryType::SspIncome->name,
                TurnoverEntryType::SspLicenseFee->name,
                TurnoverEntryType::SspOperatorFee->name,
                TurnoverEntryType::SspPublishersIncome->name,
            ];
            $table->enum('type', $allowedTypes)->index();
            $table->bigInteger('amount');
            $table->binary('ads_address')->nullable();
        });
        DB::statement('ALTER TABLE turnover_entries MODIFY ads_address varbinary(6)');

        $licenseFeeCoefficient = 0.01;
        $operatorFeeCoefficient = (1 - $licenseFeeCoefficient) * config('app.payment_tx_fee');
        $communityFeeCoefficient = (1 - $licenseFeeCoefficient - $operatorFeeCoefficient) * 0.01;

        $licenseBurntCoefficient = $licenseFeeCoefficient / ($licenseFeeCoefficient + $communityFeeCoefficient);
        DB::statement(self::SQL_DSP_ADVERTISERS_EXPENSE);
        DB::statement(self::SQL_DSP_EXPENSE);
        DB::statement(self::SQL_DSP_BURNT, [$licenseBurntCoefficient, 'DspLicenseFee']);
        DB::statement(self::SQL_DSP_BURNT, [1 - $licenseBurntCoefficient, 'DspCommunityFee']);
        DB::statement(self::SQL_DSP_OPERATOR_FEE);

        DB::statement(self::SQL_SSP_INCOME);
        DB::statement(self::SQL_SSP_LICENSE_FEE);
        DB::statement(self::SQL_SSP_PUBLISHERS_INCOME);
        DB::statement(self::SQL_SSP_OPERATOR_FEE);
    }

    public function down(): void
    {
        Schema::dropIfExists('turnover_entries');
    }
};
