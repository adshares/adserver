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

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Services\Common\UserDelete;

class DeleteUserCommand extends BaseCommand
{
    private const COMMAND_SIGNATURE = 'ops:user:delete';

    protected $signature = self::COMMAND_SIGNATURE . ' {userIds* : User ids to delete}';
    protected $description = 'Delete users';

    public function __construct(Locker $locker, private readonly UserDelete $userDelete)
    {
        parent::__construct($locker);
    }

    public function handle(): int
    {
        if (!$this->lock()) {
            $this->info(sprintf('Command %s already running', self::COMMAND_SIGNATURE));
            return self::FAILURE;
        }

        $this->info(sprintf('Start command %s', self::COMMAND_SIGNATURE));

        $userIds = array_map(fn($userId) => (int)$userId, $this->argument('userIds'));
        User::fetchByIds($userIds)
            ->tap(fn($users) => $this->info(
                sprintf('Users to delete: %d %s', $users->count(), $users->pluck('id')->join(', '))
            ))
            ->each(fn(User $user) => $this->userDelete->deleteUser($user));

        $this->info(sprintf('Finish command %s', self::COMMAND_SIGNATURE));

        return self::SUCCESS;
    }
}
