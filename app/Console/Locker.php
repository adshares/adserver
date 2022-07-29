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

namespace Adshares\Adserver\Console;

use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\SemaphoreStore;

/**
 * Class Locker created basing on Symfony\Component\Console\Command\LockableTrait
 *
 * @package Adshares\Adserver\Console
 */
class Locker
{
    private ?Lock $lock = null;

    public function lock($name = null, $blocking = false): bool
    {
        if (!class_exists(SemaphoreStore::class)) {
            throw new LogicException('To enable the locking feature you must install the symfony/lock component.');
        }

        if (null !== $this->lock) {
            throw new LogicException('A lock is already in place.');
        }

        if ($this->isSemaphoreStoreSupported()) {
            $store = new SemaphoreStore();
        } else {
            $store = new FlockStore();
        }

        $this->lock = (new LockFactory($store))->createLock($name ?: 'Locker');
        if (!$this->lock->acquire($blocking)) {
            $this->lock = null;

            return false;
        }

        return true;
    }

    public function release(): void
    {
        if ($this->lock) {
            $this->lock->release();
            $this->lock = null;
        }
    }

    private function isSemaphoreStoreSupported(): bool
    {
        return \extension_loaded('sysvsem');
    }
}
