<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CampaignRequire extends Model
{
  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
      'name', 'min', 'max'
  ];

  /**
   * The attributes that should be hidden for arrays.
   *
   * @var array
   */
  protected $hidden = [
  ];
}
