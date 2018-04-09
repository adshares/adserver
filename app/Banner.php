<?php

namespace App;

use App\ModelTraits\BinHex;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{

  use BinHex;

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
      'creative_contents', 'uuid', 'creative_type', 'creative_sha1', 'creative_width', 'creative_height',
  ];

  /**
   * The attributes that should be hidden for arrays.
   *
   * @var array
   */
  protected $hidden = [
  ];

  public function getUuidAttribute($value)
  {
      return $this->binHexAccessor($value);
  }

  public function setUuidAttribute($value)
  {
      return $this->binHexMutator($value);
  }

  public function getCreativeSha1Attribute($value)
  {
      return $this->binHexAccessor($value);
  }

  public function setCreativeSha1Attribute($value)
  {
      return $this->binHexMutator($value);
  }
}
