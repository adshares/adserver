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

namespace Adshares\Adserver\Models;

use Adshares\Common\Exception\InvalidArgumentException;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Builder
 * @property int id
 * @property int status
 */
class PaymentReport extends Model
{
    public const MIN_INTERVAL = 3600;// 1 hour

    public const STATUS_DONE = 0;

    public const STATUS_ERROR = 1;

    public const STATUS_NEW = 2;

    public const STATUS_UPDATED = 3;

    public const STATUS_PREPARED = 4;

    public static function register(int $id): self
    {
        if ($id % self::MIN_INTERVAL != 0) {
            throw new InvalidArgumentException('PaymentReport id must be multiple of ' . self::MIN_INTERVAL);
        }

        $paymentReport = self::find($id);

        if (null === $paymentReport) {
            $paymentReport = new self();
            $paymentReport->id = $id;
            $paymentReport->status = self::STATUS_NEW;
            $paymentReport->save();
        }

        return $paymentReport;
    }

    public function setDone(): void
    {
        $this->status = self::STATUS_DONE;
        $this->save();
    }

    public function setFailed(): void
    {
        $this->status = self::STATUS_ERROR;
        $this->save();
    }

    public function setNew(): void
    {
        $this->status = self::STATUS_NEW;
        $this->save();
    }

    public function setUpdated(): void
    {
        $this->status = self::STATUS_UPDATED;
        $this->save();
    }

    public function setPrepared(): void
    {
        $this->status = self::STATUS_PREPARED;
        $this->save();
    }

    public function isNew(): bool
    {
        return self::STATUS_NEW === $this->status;
    }

    public function isFailed(): bool
    {
        return self::STATUS_ERROR === $this->status;
    }

    public function isUpdated(): bool
    {
        return self::STATUS_UPDATED === $this->status;
    }

    public function isPrepared(): bool
    {
        return self::STATUS_PREPARED === $this->status;
    }

    public static function fetchById(int $id): ?self
    {
        return self::find($id);
    }

    public static function fetchByIds(array $ids): Collection
    {
        return self::whereIn('id', $ids)->get();
    }

    public static function fetchUndone(DateTime $from): Collection
    {
        return self::where('id', '>=', $from)->where('status', '>=', self::STATUS_NEW)->get();
    }

    private static function getLast(): self
    {
        return self::orderBy('id', 'desc')->first();
    }

    public static function fillMissingReports(): void
    {
        $lastId = self::getLast()->id;
        $currentId = self::getMaximalAvailableId();

        while (($lastId += self::MIN_INTERVAL) <= $currentId) {
            self::register($lastId);
        }
    }

    private static function getMaximalAvailableId(): int
    {
        return ((int)floor(time() / self::MIN_INTERVAL) - 1) * self::MIN_INTERVAL;
    }
}
