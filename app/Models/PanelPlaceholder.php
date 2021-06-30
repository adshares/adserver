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

use Adshares\Adserver\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * @property int id
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property Carbon|null deleted_at
 * @property string type
 * @property string content
 * @mixin Builder
 */
class PanelPlaceholder extends Model
{
    use SoftDeletes;

    public const MAXIMUM_CONTENT_LENGTH = 16777210;

    public const FIELD_CONTENT = 'content';

    public const FIELD_TYPE = 'type';

    public const TYPES_ALLOWED = [
        self::TYPE_INDEX_DESCRIPTION,
        self::TYPE_INDEX_KEYWORDS,
        self::TYPE_INDEX_META_TAGS,
        self::TYPE_INDEX_TITLE,
        self::TYPE_ROBOTS_TXT,
        self::TYPE_PRIVACY_POLICY,
        self::TYPE_TERMS,
    ];

    public const TYPE_INDEX_DESCRIPTION = 'index-description';

    public const TYPE_INDEX_KEYWORDS = 'index-keywords';

    public const TYPE_INDEX_META_TAGS = 'index-meta-tags';

    public const TYPE_INDEX_TITLE = 'index-title';

    public const TYPE_ROBOTS_TXT = 'robots-txt';

    public const TYPE_PRIVACY_POLICY = 'privacy-policy';

    public const TYPE_TERMS = 'terms';

    protected $fillable = [
        self::FIELD_TYPE,
        self::FIELD_CONTENT,
    ];

    protected $visible = [
        self::FIELD_CONTENT,
    ];

    public static function construct(string $type, string $content): self
    {
        return new self([self::FIELD_TYPE => $type, self::FIELD_CONTENT => $content]);
    }

    public static function register($regulations): void
    {
        if (!is_array($regulations)) {
            $regulations = [$regulations];
        }

        $types = array_map(
            function ($entry) {
                /** @var PanelPlaceholder $entry */
                return $entry->type;
            },
            $regulations
        );

        DB::beginTransaction();

        try {
            self::whereIn(self::FIELD_TYPE, $types)->delete();

            foreach ($regulations as $regulation) {
                $regulation->save();
            }
        } catch (QueryException $queryException) {
            DB::rollBack();

            throw $queryException;
        }

        DB::commit();
    }

    public static function fetchByTypes(array $types): Collection
    {
        return self::whereIn(self::FIELD_TYPE, $types)->get();
    }

    public static function fetchByType(string $type): ?self
    {
        return self::where(self::FIELD_TYPE, $type)->first();
    }
}
