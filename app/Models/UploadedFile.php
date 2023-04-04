<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\Ownership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\UuidInterface;

/**
 * @property int id
 * @property Carbon created_at
 * @property Carbon|null deleted_at
 * @property string uuid
 * @property string type
 * @property string medium
 * @property string|null vendor
 * @property string mime
 * @property string|null $scope
 * @property string content
 *
 * @mixin Builder
 */
class UploadedFile extends Model
{
    use AutomateMutators;
    use BinHex;
    use HasFactory;
    use Ownership;

    public $timestamps = false;

    protected $dates = [
        'created_at',
        'deleted_at',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    protected $fillable = [
        'type',
        'medium',
        'vendor',
        'mime',
        'scope',
        'content',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];

    public static function fetchByUuidOrFail(UuidInterface $uuid): self
    {
        $file = (new UploadedFile())->where('uuid', $uuid->getBytes())->first();
        if (null === $file) {
            throw new ModelNotFoundException(sprintf('No query results for file %s', $uuid));
        }
        return $file;
    }
}
