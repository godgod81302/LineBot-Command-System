<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class GroupUserPair extends Model
{
  public $timestamps = false;
  protected $fillable = ['line_group_id', 'line_user_id'];

  public function group(){
    return $this->hasOne(LineGroup::class);
  }

  public function user(){
    return $this->hasOne(LineUser::class);
  }
}
