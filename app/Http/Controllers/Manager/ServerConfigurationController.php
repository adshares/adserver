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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Rules\AccountIdRule;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ServerConfigurationController extends Controller
{
    private const KEYS_MAIL = [
        Config::SUPPORT_EMAIL,
        Config::TECHNICAL_EMAIL,
    ];

    private const RULES_MAIL = [
        Config::SUPPORT_EMAIL => 'email|max:255',
        Config::TECHNICAL_EMAIL => 'email|max:255',
    ];

    private const KEYS_WALLET = [
        Config::COLD_WALLET_ADDRESS,
        Config::COLD_WALLET_IS_ACTIVE,
        Config::HOT_WALLET_MAX_VALUE,
        Config::HOT_WALLET_MIN_VALUE,
    ];

    private const RULES_WALLET = [
        Config::COLD_WALLET_IS_ACTIVE => 'boolean',
        Config::HOT_WALLET_MAX_VALUE => [
            'integer',
            'min:0',
            'max:100000000000000000',
            'gt:' . Config::HOT_WALLET_MIN_VALUE,
        ],
        Config::HOT_WALLET_MIN_VALUE => [
            'integer',
            'min:0',
            'max:100000000000000000',
        ],
    ];

    public function fetchMail(): JsonResponse
    {
        return self::json($this->fetchData(self::KEYS_MAIL));
    }

    public function storeMail(Request $request): JsonResponse
    {
        $validated = $request->validate(self::RULES_MAIL);
        $this->storeData($validated);
        return self::json();
    }

    public function fetchWallet(): JsonResponse
    {
        return self::json($this->fetchData(self::KEYS_WALLET));
    }

    public function storeWallet(Request $request): JsonResponse
    {
        $hotWalletValues = $this->fetchData([Config::HOT_WALLET_MAX_VALUE, Config::HOT_WALLET_MIN_VALUE]);
        $input = array_merge($hotWalletValues, $request->input());
        $rules = array_merge(
            self::RULES_WALLET,
            [
                Config::COLD_WALLET_ADDRESS => new AccountIdRule([new AccountId(config('app.adshares_address'))]),
            ]
        );
        $validated = Validator::make(
            $input,
            $rules,
            ['hotwallet-max-value.gt' => 'The hotwallet-max-value must be greater than hotwallet-min-value']
        )->validated();

        if (isset($validated[Config::COLD_WALLET_IS_ACTIVE])) {
            $validated[Config::COLD_WALLET_IS_ACTIVE] = (int)$validated[Config::COLD_WALLET_IS_ACTIVE];
        }

        $this->storeData($validated);
        return self::json();
    }

    /**
     * @deprecated general purpose endpoint can be removed at any time
     */
    public function fetch(Request $request): JsonResponse
    {
        $content = json_decode($request->getContent(), true);

        return self::json($this->fetchData($content));
    }

    /**
     * @deprecated general purpose endpoint can be removed at any time
     */
    public function store(Request $request): JsonResponse
    {
        $content = $request->input();

        $this->storeData($content);

        return self::json();
    }

    private function fetchData(array $keys): array
    {
        return Config::whereIn('key', $keys)->get()
            ->pluck('value', 'key')
            ->toArray();
    }

    private function storeData(array $data): void
    {
        DB::beginTransaction();
        try {
            foreach ($data as $key => $value) {
                Config::upsertByKey($key, $value);
            }
            DB::commit();
        } catch (Throwable $exception) {
            Log::error(sprintf('Exception during server configuration update (%s)', $exception->getMessage()));
            DB::rollBack();
            throw new RuntimeException('Cannot store configuration');
        }
    }
}
