<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

declare(strict_types = 1);

namespace Adshares\Adserver\Console\Commands;
use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Models\User;
use Adshares\Common\Domain\ValueObject\Email;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use function str_random;
use function substr;

class CreateAdminUserCommand extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ops:admin:create';

    protected $description = 'Create an admin user';

    public function handle(): void
    {
        $input = $this->ask('Please type an admin email');

        if (!$input) {
            $this->error('Email address cannot be empty.');
            return;
        }

        try {
            $email = new Email($input);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return;
        }


        $name = 'admin';
        $password = substr(Hash::make(str_random(8)), -8);
        User::createAdmin($email, $name, $password);

        $this->info(sprintf('Password: %s', $password));
    }
}
