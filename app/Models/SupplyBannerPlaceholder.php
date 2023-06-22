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
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int id
 * @property string uuid
 * @property string|null parent_uuid
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property Carbon|null deleted_at
 * @property string medium
 * @property string size
 * @property string type
 * @property string mime
 * @property bool is_default
 * @property string content
 * @property string checksum
 * @property string serve_url
 * @mixin Builder
 */
class SupplyBannerPlaceholder extends Model
{
    use AutomateMutators;
    use BinHex;
    use HasFactory;
    use SoftDeletes;

    private const COLUMNS_WITHOUT_CONTENT = [
        'id',
        'uuid',
        'parent_uuid',
        'created_at',
        'updated_at',
        'deleted_at',
        'medium',
        'size',
        'type',
        'mime',
        'is_default',
        'checksum',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected $dates = [
        'deleted_at',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    protected $fillable = [
        'medium',
        'size',
        'type',
        'mime',
        'is_default',
        'parent_uuid',
        'content',
        'checksum',
    ];

    protected array $traitAutomate = [
        'uuid' => 'BinHex',
        'checksum' => 'BinHex',
    ];

    public static function register(
        string $medium,
        string $size,
        string $type,
        string $mime,
        string $content,
        bool $isDefault,
        ?string $parentUuid,
    ): self {
        $model = new self();
        $model->fill(
            [
                'medium' => $medium,
                'size' => $size,
                'type' => $type,
                'mime' => $mime,
                'is_default' => $isDefault,
                'parent_uuid' => null === $parentUuid ? null : hex2bin($parentUuid),
                'content' => $content,
                'checksum' => sha1($content),
            ]
        );
        $model->save();
        return $model;
    }

    public static function fetchOne(
        string $medium,
        array $scopes,
        ?array $types = null,
        ?array $mimes = null,
        bool $withThrashed = false,
    ): ?self {
        $query = self::query()
            ->where('medium', $medium)
            ->whereIn('size', $scopes);

        if (null !== $types) {
            $query->whereIn('type', $types);
        }
        if (null !== $mimes) {
            $query->whereIn('mime', $mimes);
        }
        if ($withThrashed) {
            $query->where('is_default', true)->withTrashed();
        }

        return $query->first(self::COLUMNS_WITHOUT_CONTENT);
    }

    public static function fetchByPublicId(string $publicId, bool $withContent = false): ?self
    {
        return self::query()
            ->where('uuid', hex2bin($publicId))
            ->first($withContent ? '*' : self::COLUMNS_WITHOUT_CONTENT);
    }

    public function fetchDerived(): Collection
    {
        return SupplyBannerPlaceholder::query()
            ->where('parent_uuid', hex2bin($this->uuid))
            ->get();
    }

    public function getServeUrlAttribute(): string
    {
        return ServeDomain::changeUrlHost(
            (new SecureUrl(
                route(
                    'placeholder-serve',
                    [
                        'banner_id' => $this->uuid,
                    ]
                )
            ))->toString()
        );
    }
}
