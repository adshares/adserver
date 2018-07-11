<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NetworkCampaign extends Model
{
    use AutomateMutators;
    use BinHex;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $dates = [
        'time_start', 'time_end',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'source_created_at', 'source_updated_at',
        'source_host', 'adshares_address',
        'landing_url', 'max_cpm', 'max_cpc', 'budget_per_hour', 'time_start', 'time_end',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'id',
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
        return $this->hasMany('Adshares\Adserver\Models\NetworkBanner');
    }

    public function campaignExcludes()
    {
        return $this->hasMany('Adshares\Adserver\Models\NetworkCampaignExclude');
    }

    public function campaignRequires()
    {
        return $this->hasMany('Adshares\Adserver\Models\NetworkCampaignRequire');
    }

    protected static function convertTimestampsToSourceTimestamps($array)
    {
        $array['source_created_at'] = $array['created_at'];
        $array['source_updated_at'] = $array['updated_at'];
        unset($array['created_at']);
        unset($array['updated_at']);

        return $array;
    }

    public function getAdselectJson()
    {
        $json = [
            'campaign_id' => $this->source_host.'/'.$this->uuid,
            // TODO: discuss, missing in inventory
            // 'advertiser_id' => $this->source_host . '/'. $this->getAdvertiserId(),
            'time_start' => $this->time_start->getTimestamp(),
            'time_end' => $this->time_end->getTimestamp(),
            // 'filters' => Filter::getFilter($this->getRequire(), $this->getExclude()),
            'keywords' => [
                'source_host' => $this->source_host,
                'adshares_address' => $this->adshares_address,
                // 'landing_host' => parse_url($this->getLandingUrl(), PHP_URL_HOST), // TODO: missing in inventory
                // 'landing_url' => $this->landing_url, // TODO: missing in inventory
            ],
        ];

        $banners = [];

        foreach ($this->banners as $banner) {
            $banners[] = $banner->getAdselectJson();
        }

        $json['banners'] = $banners;

        return $json;
    }

    protected static function jsonDataMakeUUIDKeys(array &$data)
    {
        foreach ($data['banners'] as $i => $d) {
            $data['banners'][$d['uuid']] = self::convertTimestampsToSourceTimestamps($d);
            unset($data['banners'][$i]);
        }
        foreach ($data['campaign_excludes'] as $i => $d) {
            $data['campaign_excludes'][$d['uuid']] = self::convertTimestampsToSourceTimestamps($d);
            unset($data['campaign_excludes'][$i]);
        }
        foreach ($data['campaign_requires'] as $i => $d) {
            $data['campaign_requires'][$d['uuid']] = self::convertTimestampsToSourceTimestamps($d);
            unset($data['campaign_requires'][$i]);
        }
    }

    public static function fromJsonData(array $data)
    {
        DB::beginTransaction();
        $campaign = self::with(
            'Banners',
            'CampaignExcludes',
            'CampaignRequires'
        )->where('uuid', hex2bin($data['uuid']))->lockForUpdate()->first();
        if (empty($campaign)) {
            $campaign = self::fromJsonDataNew($data);
        } else {
            $campaign = self::fromJsonDataUpdate($campaign, $data);
        }
        DB::commit();

        return $campaign;
    }

    public static function fromJsonDataNew(array $data)
    {
        $data = self::convertTimestampsToSourceTimestamps($data);
        $campaign = self::create($data);

        foreach ($data['banners'] as $d) {
            $d = self::convertTimestampsToSourceTimestamps($d);
            $d['network_campaign_id'] = $campaign->id;
            NetworkBanner::create($d);
        }
        foreach ($data['campaign_excludes'] as $d) {
            $d = self::convertTimestampsToSourceTimestamps($d);
            $d['network_campaign_id'] = $campaign->id;
            NetworkCampaignExclude::create($d);
        }
        foreach ($data['campaign_requires'] as $d) {
            $d = self::convertTimestampsToSourceTimestamps($d);
            $d['network_campaign_id'] = $campaign->id;
            NetworkCampaignRequire::create($d);
        }

        return $campaign;
    }

    protected static function fromJsonDataUpdateObjects(Collection $collection, array $data)
    {
        foreach ($collection as $o) {
            if (empty($data[$o->uuid])) {
                $o->delete();
                continue;
            }
            if ($data[$o->uuid]['source_updated_at'] === $o->source_updated_at) {
                continue;
            }
            $o->fill($data[$o->uuid]);
            $o->save();
        }
    }

    public static function fromJsonDataUpdate(NetworkCampaign $campaign, array $data)
    {
        $data = self::convertTimestampsToSourceTimestamps($data);
        if ($campaign->source_updated_at === $data['source_updated_at']) {
            return $campaign;
        }

        $campaign->fill($data);
        $campaign->save();

        self::jsonDataMakeUUIDKeys($data);
        self::fromJsonDataUpdateObjects($campaign->banners, $data['banners']);
        self::fromJsonDataUpdateObjects($campaign->campaignExcludes, $data['campaign_excludes']);
        self::fromJsonDataUpdateObjects($campaign->campaignRequires, $data['campaign_requires']);

        return $campaign;
    }
}
