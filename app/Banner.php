<?php

namespace App;

use App\ModelTraits\AutomateMutators;
use App\ModelTraits\BinHex;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{

  use AutomateMutators;
  use BinHex;

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
      'campaign_id', 'creative_contents', 'uuid', 'creative_type', 'creative_sha1', 'creative_width', 'creative_height',
  ];

  /**
   * The attributes that should be hidden for arrays.
   *
   * @var array
   */
  protected $hidden = [
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
}
