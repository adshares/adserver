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

use Adshares\Adserver\Events\CreativeSha1;
use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Banner extends Model
{
    public const IMAGE_TYPE = 0;
    public const HTML_TYPE = 1;

    use AutomateMutators;
    use BinHex;
    use SoftDeletes;
    protected $dates = [
        'deleted_at',
    ];
    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
        'saving' => CreativeSha1::class,
    ];
    protected $fillable = [
        'uuid',
        'campaign_id',
        'creative_contents',
        'creative_type',
        'creative_sha1',
        'creative_width',
        'creative_height',
        'name',
    ];
    protected $hidden = [
        'id',
        'creative_contents',
        'campaign_id',
        'deleted_at',
    ];
    protected $traitAutomate = [
        'uuid' => 'BinHex',
        'creative_sha1' => 'BinHex',
    ];

    public static function type($type)
    {
        if ($type === self::IMAGE_TYPE) {
            return 'image';
        }

        return 'html';
    }

    public static function size($size)
    {
        if (!isset(Zone::ZONE_SIZE[$size])) {
            throw new \RuntimeException(sprintf('Wrong image size.'));
        }

        return Zone::ZONE_SIZE[$size];
    }

    public function campaign()
    {
        return $this->belongsTo('Adshares\Adserver\Models\Campaign');
    }

    protected function toArrayExtras($array)
    {
        $array['serve_url'] = route('banner-serve', ['id' => $this->id]);
        $array['view_url'] = route('log-network-view', ['id' => $this->id]);
        $array['click_url'] = route('log-network-click', ['id' => $this->id]);

        if ($this->type === self::HTML_TYPE) {
            $array['html'] = $this->creative_contents;
        }

        if ($this->type === self::IMAGE_TYPE) {
            $array['image_url'] = $array['serve_url'];
        }

        return $array;
    }
}
