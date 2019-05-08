<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\Serialize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Builder
 * @property string uuid
 */
class Token extends Model
{
    use AutomateMutators;
    use BinHex;
    use Serialize;

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    protected $fillable = [
        'multi_usage',
        'payload',
        'tag',
        'user_id',
        'valid_until',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
        'payload' => 'Serialize',
    ];

    public static function canGenerate(int $user_id, $tag, int $older_then_seconds)
    {
        if (self::where('user_id', $user_id)->where('tag', $tag)->where(
            'created_at',
            '>',
            date('Y-m-d H:i:s', time() - $older_then_seconds)
        )->count()) {
            return false;
        }

        return true;
    }

    public static function check($uuid, int $user_id = null, $tag = null)
    {
        $q = self::where('uuid', hex2bin($uuid))->where('valid_until', '>', date('Y-m-d H:i:s'));
        if (!empty($user_id)) {
            $q->where('user_id', $user_id);
        }
        if (!empty($tag)) {
            $q->where('tag', $tag);
        }
        $token = $q->first();
        if (empty($token)) {
            return false;
        }
        $return = $token->toArray();
        if ($token->multi_usage) {
            return $return;
        }
        $token->delete();

        return $token->toArray();
    }

    public static function extend($uuid, int $seconds_valid, $user_id = null, $tag = null)
    {
        $q = self::where('uuid', hex2bin($uuid))->where('valid_until', '>', date('Y-m-d H:i:s'));
        if (!empty($user_id)) {
            $q->where('user_id', $user_id);
        }
        if (!empty($tag)) {
            $q->where('tag', $tag);
        }
        $token = $q->first();

        if (empty($token)) {
            return false;
        }
        $token->valid_until = date('Y-m-d H:i:s', time() + $seconds_valid);
        $token->save();

        return true;
    }

    public static function generate(
        string $tag,
        int $valid_until_seconds,
        int $user_id = null,
        $payload = null,
        bool $multi_usage = false
    ) {
        $valid_until = date('Y-m-d H:i:s', time() + $valid_until_seconds);
        $token = self::create(compact('user_id', 'tag', 'payload', 'valid_until', 'multi_usage'));

        return $token->uuid;
    }

    public static function impersonation(User $user): self
    {
        return self::generate('impersonation', 24 * 3600, $user->id, null, true);
    }
}
