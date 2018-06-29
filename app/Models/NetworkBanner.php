<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;

use Illuminate\Database\Eloquent\Model;

class NetworkBanner extends Model
{
    use AutomateMutators;
    use BinHex;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
      'uuid', 'network_campaign_id',
      'source_created_at', 'source_updated_at',
      'serve_url','click_url', 'view_url',
      'creative_contents', 'creative_type', 'creative_sha1', 'creative_width', 'creative_height',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
      'id', 'network_campaign_id'
    ];

    /**
    * The attributes that use some Models\Traits with mutator settings automation
    *
    * @var array
    */
    protected $traitAutomate = [
      'uuid' => 'BinHex',
      'creative_sha1' => 'BinHex',
    ];

    public function getAdselectJson()
    {
        return [
            'banner_id' => $this->uuid,
            'banner_size' => $this->creative_width . 'x' . $this->creative_height,
            'keywords' => [
                'type' => $this->creative_type,
            ]
        ];
    }
}
