<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class NameRecord extends Model
{
  public $timestamps = false;
  protected $guarded = ['id'];

  public function recordable(){
    return $this->morphTo();
  }
}
