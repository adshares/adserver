<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

use Adshares\Adserver\Models\AdsTxIn;
use Adshares\Adserver\Models\UserLedgerEntry;
use Faker\Generator as Faker;

$factory->define(UserLedgerEntry::class, function (Faker $faker) {
    $adsTx = new AdsTxIn();
    $accNum = str_pad(dechex(mt_rand(0, 2047)), 8, '0', STR_PAD_LEFT);
    $adsTx->txid = "0001:$accNum:0001";
    $adsTx->amount = 1;
    $adsTx->address = "0001-$accNum-XXXX";
    $res = $adsTx->save();

    return [
        'amount' => '',
        'status' => UserLedgerEntry::STATUS_ACCEPTED,
        'address_from' => '',
        'address_to' => '',
    ];
});
