<?php

namespace Adshares\Adserver;

use Adshares\Adserver\Events\CreativeSha1;
use Adshares\Adserver\Events\GenerateUUID;

use Adshares\Adserver\ModelTraits\AutomateMutators;
use Adshares\Adserver\ModelTraits\BinHex;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
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
        'saving' => CreativeSha1::class,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
      'uuid', 'campaign_id',
      'creative_contents', 'creative_type', 'creative_sha1', 'creative_width', 'creative_height',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
      'id','creative_contents','campaign_id'
    ];

    /**
    * The attributes that use some ModelTraits with mutator settings automation
    *
    * @var array
    */
    protected $traitAutomate = [
      'uuid' => 'BinHex',
      'creative_sha1' => 'BinHex',
    ];

    public function campaign()
    {
        return $this->belongsTo('Adshares\Adserver\Campaign');
    }

    /**
    * check toArrayExtrasCheck() in AutomateMutators trait
    */
    protected function toArrayExtras($array)
    {
        $array['serve_url'] = route('banner-serve', ['id'=>$this->id]);
        $array['view_url'] = route('banner-view', ['id'=>$this->id]);
        $array['click_url'] = route('banner-click', ['id'=>$this->id]);
        return $array;
    }
}
