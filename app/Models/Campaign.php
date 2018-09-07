<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute it and/or modify it
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
 * along with AdServer.  If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use AutomateMutators;
    use BinHex;

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid', 'landing_url', 'max_cpm', 'max_cpc', 'budget_per_hour', 'time_start', 'time_end', 'require_count',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'id', 'user_id',
    ];

    /**
     * The attributes that use some Models\Traits with mutator settings automation.
     *
     * @var array
     */
    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];

    public function banners()
    {
        return $this->hasMany('Adshares\Adserver\Models\Banner');
    }

    public function campaignExcludes()
    {
        return $this->hasMany('Adshares\Adserver\Models\CampaignExclude');
    }

    public function campaignRequires()
    {
        return $this->hasMany('Adshares\Adserver\Models\CampaignRequire');
    }

    public static function getWithReferences($listDeletedCampaigns)
    {
        $q = Campaign::with('Banners', 'CampaignExcludes', 'CampaignRequires');

        if ($listDeletedCampaigns) {
            return $q->get();
        }

        return $q->whereNull('deleted_at')->get();
    }
}
