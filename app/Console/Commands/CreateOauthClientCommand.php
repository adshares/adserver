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

declare(strict_types=1);

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;
use Throwable;

class CreateOauthClientCommand extends BaseCommand
{
    protected $signature = 'ops:passport-client:create {name} {redirect_uri}';

    protected $description = 'Create a client for issuing access tokens';

    public function handle(): int
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->signature . ' already running');
            return 1;
        }

        $user = User::fetchOrRegisterSystemUser();
        $options = [
            '--name' => $this->argument('name'),
            '--redirect_uri' => $this->argument('redirect_uri'),
            '--user_id' => $user->id,
        ];

        DB::beginTransaction();
        try {
            $this->revokeClient();
            Artisan::call('passport:client', $options, $this->getOutput());
            DB::commit();
        } catch (Throwable $exception) {
            Log::error(sprintf('Creating client failed (%s)', $exception->getMessage()));
            DB::rollBack();
            return 1;
        }
        return 0;
    }

    private function revokeClient(): void
    {
        $builder = Passport::client()->where('name', $this->argument('name'))->where('revoked', 0);
        $builder->get()->each(fn($item) => $item->tokens()->update(['revoked' => 1]));
        $builder->update(['revoked' => 1]);
    }
}