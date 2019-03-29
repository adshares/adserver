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

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;

class Regulation extends Model
{
    public const MAXIMUM_CONTENT_LENGTH = 16777210;

    use SoftDeletes;

    public const TYPE_TERMS = 'terms';

    public const TYPE_PRIVACY_POLICY = 'privacy policy';

    private const FIELD_CONTENT = 'content';

    private const FIELD_TYPE = 'type';

    protected $fillable = [
        self::FIELD_TYPE,
        self::FIELD_CONTENT,
    ];

    protected $visible = [
        self::FIELD_CONTENT,
    ];

    private static function construct(string $type, string $content): self
    {
        DB::beginTransaction();

        try {
            self::where(self::FIELD_TYPE, $type)->delete();

            $regulation = new self([self::FIELD_TYPE => $type, self::FIELD_CONTENT => $content]);
            $regulation->save();
        } catch (QueryException $queryException) {
            DB::rollBack();

            throw $queryException;
        }

        DB::commit();

        return $regulation;
    }

    private static function fetch(string $type): self
    {
        return self::where(self::FIELD_TYPE, $type)->firstOrFail();
    }

    public static function addTerms(string $terms): self
    {
        return self::construct(self::TYPE_TERMS, $terms);
    }

    public static function fetchTerms(): self
    {
        return self::fetch(self::TYPE_TERMS);
    }

    public static function addPrivacyPolicy(string $privacyPolicy): self
    {
        return self::construct(self::TYPE_PRIVACY_POLICY, $privacyPolicy);
    }

    public static function fetchPrivacyPolicy(): self
    {
        return self::fetch(self::TYPE_PRIVACY_POLICY);
    }
}
