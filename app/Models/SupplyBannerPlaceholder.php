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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int id
 * @property string uuid
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property Carbon|null deleted_at
 * @property string medium
 * @property string|null vendor
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

    private const TYPE_HTML = 'html';
    private const TYPE_IMAGE = 'image';
    private const TYPE_DIRECT_LINK = 'direct';
    private const TYPE_VIDEO = 'video';
    private const TYPE_MODEL = 'model';

    public const ALLOWED_TYPES = [
        self::TYPE_HTML,
        self::TYPE_IMAGE,
        self::TYPE_DIRECT_LINK,
        self::TYPE_VIDEO,
        self::TYPE_MODEL,
    ];
    private const COLUMNS_WITHOUT_CONTENT = [
        'id',
        'uuid',
        'created_at',
        'updated_at',
        'deleted_at',
        'medium',
        'vendor',
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
        'vendor',
        'size',
        'type',
        'mime',
        'is_default',
        'content',
        'checksum',
    ];

    protected array $traitAutomate = [
        'uuid' => 'BinHex',
        'checksum' => 'BinHex',
    ];

    public static function register(
        string $medium,
        ?string $vendor,
        string $size,
        string $type,
        string $mime,
        string $content,
        bool $isDefault,
    ): self {
        $model = new self();
        $model->fill(
            [
                'medium' => $medium,
                'vendor' => $vendor,
                'size' => $size,
                'type' => $type,
                'mime' => $mime,
                'is_default' => $isDefault,
                'content' => $content,
                'checksum' => sha1($content),
            ]
        );
        $model->save();
        return $model;
    }

    public static function fetch(
        string $medium,
        ?string $vendor,
        array $scopes,
        ?array $types = null,
        ?array $mimes = null,
        bool $withThrashed = false,
    ): ?self {
        $query = self::query()
            ->where('medium', $medium)
            ->whereIn('size', $scopes);

        if (null !== $vendor) {
            $query->where('vendor', $vendor);
        }
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
