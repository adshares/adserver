<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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
use Adshares\Adserver\Utilities\SqlUtils;
use Adshares\Common\Domain\ValueObject\Email;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;

use function str_random;
use function substr;
use function env;

class CreateAdminUserCommand extends BaseCommand
{
    protected $signature = 'ops:admin:create {--password=}';

    protected $description = 'Create an admin user';

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->signature . ' already running');

            return;
        }

        $password = $this->option('password');

        if (!$password) {
            $password = env('TMP_ADMIN_PASSWORD');
        }

        $input = $this->ask('Please type an admin email', config('app.adshares_operator_email'));

        if (!$input) {
            $this->error('Email address cannot be empty');

            exit(0);
        }

        try {
            $email = new Email($input);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            exit(0);
        }

        $name = 'admin';
        if (!$password) {
            $password = substr(Hash::make(str_random(8)), -8);
        }

        try {
            User::createAdmin($email, $name, $password);
        } catch (QueryException $exception) {
            if (SqlUtils::isDuplicatedEntry($exception)) {
                $this->error(sprintf('User %s already exists', $email->toString()));

                exit(0);
            }

            $this->error($exception->getMessage());

            exit(0);
        }


        $this->info(sprintf('Password: %s', $password));
    }
}
