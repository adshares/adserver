<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
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
            $table->timestamp('computed_at');
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
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_credit_logs');

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['experiment_budget', 'experiment_end_at']);
        });
    }
};
