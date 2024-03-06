<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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

use Adshares\Supply\Domain\ValueObject\TurnoverEntryType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $allowedTypes = [
            TurnoverEntryType::DspAdvertisersExpense,
            TurnoverEntryType::DspLicenseFee,
            TurnoverEntryType::DspOperatorFee,
            TurnoverEntryType::DspCommunityFee,
            TurnoverEntryType::DspExpense,
            TurnoverEntryType::SspIncome,
            TurnoverEntryType::SspLicenseFee,
            TurnoverEntryType::SspOperatorFee,
            TurnoverEntryType::SspPublishersIncome,
            TurnoverEntryType::DspJoiningFeeIncome,
            TurnoverEntryType::SspJoiningFeeExpense,
        ];
        $allowed = join(',', array_map(fn($status) => "'" . $status->value . "'", $allowedTypes));
        DB::statement(
            sprintf(
                'ALTER TABLE `turnover_entries` CHANGE `type` `type` ENUM(%s) NOT NULL',
                $allowed,
            )
        );

        Schema::table('campaigns', function (Blueprint $table) {
            $table->bigInteger('experiment_budget')
                ->nullable(false)
                ->default('0');
            $table->timestamp('experiment_end_at')->nullable();
        });

        Schema::create('event_credit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();

            $table->binary('uuid');
            $table->timestamp('computed_at')->index();
            $table->binary('advertiser_id');
            $table->binary('campaign_id');
            $table->binary('pay_to');

            $table->bigInteger('event_value_currency')->unsigned()->nullable();
            $table->decimal('exchange_rate', 9, 5)->nullable();
            $table->bigInteger('event_value')->unsigned()->nullable();

            $table->bigInteger('license_fee')->unsigned()->nullable();
            $table->bigInteger('operator_fee')->unsigned()->nullable();
            $table->bigInteger('community_fee')->unsigned()->nullable();
            $table->bigInteger('paid_amount')->unsigned()->nullable();
            $table->integer('payment_id')->nullable()->index();
        });

        DB::statement('ALTER TABLE event_credit_logs MODIFY uuid VARBINARY(16)');
        DB::statement('ALTER TABLE event_credit_logs MODIFY advertiser_id VARBINARY(16)');
        DB::statement('ALTER TABLE event_credit_logs MODIFY campaign_id VARBINARY(16)');
        DB::statement('ALTER TABLE event_credit_logs MODIFY pay_to VARBINARY(6)');

        Schema::table('event_credit_logs', function (Blueprint $table) {
            $table->index('pay_to');
            $table->unique('uuid');
        });

        Schema::create('ads_payment_metas', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('ads_payment_id');
            $table->foreign('ads_payment_id')
                ->references('id')
                ->on('ads_payments')
                ->onUpdate('RESTRICT')
                ->onDelete('CASCADE');
            $table->json('meta');
        });

        Schema::create('network_credit_payments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('ads_payment_id');
            $table->foreign('ads_payment_id')
                ->references('id')
                ->on('ads_payments')
                ->onUpdate('RESTRICT')
                ->onDelete('CASCADE');
            $table->unsignedBigInteger('network_campaign_id');
            $table->foreign('network_campaign_id')
                ->references('id')
                ->on('network_campaigns')
                ->onUpdate('RESTRICT')
                ->onDelete('CASCADE');
            $table->timestamp('pay_time')->index();
            $table->unsignedBigInteger('total_amount');
            $table->unsignedBigInteger('license_fee');
            $table->unsignedBigInteger('operator_fee');
            $table->unsignedBigInteger('paid_amount');
            $table->decimal('exchange_rate', 9, 5);
            $table->unsignedBigInteger('paid_amount_currency');
        });

        Schema::create('ssp_hosts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
            $table->binary('ads_address');
            $table->boolean('accepted')->default(false);
        });
        DB::statement('ALTER TABLE ssp_hosts MODIFY ads_address VARBINARY(6)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ssp_hosts');
        Schema::dropIfExists('network_credit_payments');
        Schema::dropIfExists('ads_payment_metas');
        Schema::dropIfExists('event_credit_logs');

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['experiment_budget', 'experiment_end_at']);
        });

        DB::delete('DELETE FROM turnover_entries WHERE type IN (?, ?)', [
            TurnoverEntryType::DspJoiningFeeIncome->value,
            TurnoverEntryType::SspJoiningFeeExpense->value,
        ]);
        $allowedTypes = [
            TurnoverEntryType::DspAdvertisersExpense,
            TurnoverEntryType::DspLicenseFee,
            TurnoverEntryType::DspOperatorFee,
            TurnoverEntryType::DspCommunityFee,
            TurnoverEntryType::DspExpense,
            TurnoverEntryType::SspIncome,
            TurnoverEntryType::SspLicenseFee,
            TurnoverEntryType::SspOperatorFee,
            TurnoverEntryType::SspPublishersIncome,
        ];
        $allowed = join(',', array_map(fn($status) => "'" . $status->value . "'", $allowedTypes));
        DB::statement(
            sprintf(
                'ALTER TABLE `turnover_entries` CHANGE `type` `type` ENUM(%s) NOT NULL',
                $allowed,
            )
        );
    }
};
