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

use Adshares\Adserver\Exceptions\MissingInitialConfigurationException;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\Config;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AddMediumToBidStrategy extends Migration
{
    public function up(): void
    {
        Schema::table('bid_strategy', function (Blueprint $table) {
            $table->string('medium', 16)->default('web');
            $table->string('vendor', 32)->nullable();
            $table->boolean('is_default')->default(0);
        });

        $this->setupDefaultBidStrategyForWeb();
        $this->insertDefaultBidStrategiesForMediaOtherThanWeb();
    }

    public function down(): void
    {
        $now = new DateTimeImmutable();
        $uuid = bin2hex(
            DB::selectOne('SELECT `uuid` FROM bid_strategy WHERE user_id = 0 AND `medium` = ? LIMIT 1', ['web'])->uuid
        );

        Schema::table('bid_strategy', function (Blueprint $table) {
            $table->dropColumn(['medium', 'vendor', 'is_default']);
        });

        DB::insert(
            'INSERT INTO configs (`key`, value, created_at, updated_at) VALUES (?,?,?,?)',
            [Config::BID_STRATEGY_UUID_DEFAULT, $uuid, $now, $now]
        );
    }

    private function setupDefaultBidStrategyForWeb(): void
    {
        $uuid = DB::selectOne(
            'SELECT `value` FROM configs WHERE `key` = ?',
            [Config::BID_STRATEGY_UUID_DEFAULT]
        )->value;
        DB::update('UPDATE bid_strategy SET `is_default` = 1 WHERE `uuid` = ?', [hex2bin($uuid)]);
        DB::delete('DELETE FROM configs WHERE `key`=?', [Config::BID_STRATEGY_UUID_DEFAULT]);
    }

    private function insertDefaultBidStrategiesForMediaOtherThanWeb(): void
    {
        /** @var ConfigurationRepository $configurationRepository */
        $configurationRepository = app(ConfigurationRepository::class);
        try {
            $taxonomy = $configurationRepository->fetchTaxonomy();
        } catch (MissingInitialConfigurationException $exception) {
            Log::info(sprintf('Fetch taxonomy exception: %s', $exception->getMessage()));
            return;
        }

        foreach ($taxonomy->getMedia() as $mediumObject) {
            BidStrategy::registerIfMissingDefault(
                sprintf('Default %s', $mediumObject->getVendorLabel() ?: $mediumObject->getLabel()),
                $mediumObject->getName(),
                $mediumObject->getVendor(),
            );
        }
    }
}
