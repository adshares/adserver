<?php

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\EventConversionLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventConversionLogs extends Migration
{
    public function up(): void
    {
        Schema::create(
            'event_conversion_logs',
            function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->timestamps();

                $table->binary('case_id')->nullable(false);
                $table->binary('event_id')->nullable(false);
                $table->binary('tracking_id')->nullable(false);
                $table->binary('banner_id')->nullable(false);
                $table->binary('publisher_id')->nullable();
                $table->binary('advertiser_id')->nullable(false);
                $table->binary('campaign_id')->nullable(false);
                $table->binary('zone_id')->nullable();
                $table->string('event_type', 16);

                $table->binary('pay_to')->nullable();
                $table->binary('ip');
                $table->json('headers')->nullable();

                $table->text('our_context')->nullable();
                $table->text('their_context')->nullable();

                $table->decimal('human_score', 3, 2)->nullable();
                $table->text('our_userdata')->nullable();
                $table->text('their_userdata')->nullable();

                $table->bigInteger('event_value_currency')->unsigned()->nullable();
                $table->decimal('exchange_rate', 9, 5)->nullable();
                $table->bigInteger('event_value')->unsigned()->nullable();
                $table->bigInteger('license_fee')->unsigned()->nullable();
                $table->bigInteger('operator_fee')->unsigned()->nullable();
                $table->bigInteger('paid_amount')->unsigned()->nullable();
                $table->integer('payment_id')->nullable()->index();

                $table->tinyInteger('reason')->unsigned()->nullable();
                $table->tinyInteger('is_view_clicked')->unsigned()->default(0);
                $table->string('domain', 255)->nullable();
                $table->binary('user_id')->nullable();
            }
        );

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE event_conversion_logs MODIFY case_id varbinary(16)');
            DB::statement('ALTER TABLE event_conversion_logs MODIFY event_id varbinary(16)');
            DB::statement('ALTER TABLE event_conversion_logs MODIFY tracking_id varbinary(16)');
            DB::statement('ALTER TABLE event_conversion_logs MODIFY banner_id varbinary(16) NOT NULL');
            DB::statement('ALTER TABLE event_conversion_logs MODIFY publisher_id varbinary(16)');
            DB::statement('ALTER TABLE event_conversion_logs MODIFY advertiser_id varbinary(16)');
            DB::statement('ALTER TABLE event_conversion_logs MODIFY campaign_id varbinary(16)');
            DB::statement('ALTER TABLE event_conversion_logs MODIFY zone_id varbinary(16)');
            DB::statement('ALTER TABLE event_conversion_logs MODIFY pay_to varbinary(6)');
            DB::statement('ALTER TABLE event_conversion_logs MODIFY ip VARBINARY(16)');
            DB::statement('ALTER TABLE event_conversion_logs MODIFY user_id varbinary(16)');
        }

        Schema::table(
            'event_conversion_logs',
            function (Blueprint $table) {
                $table->unique('event_id');
                $table->index('created_at', EventConversionLog::INDEX_CREATED_AT);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('event_conversion_logs');
    }
}
