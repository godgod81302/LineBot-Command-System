<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class LineGroup extends Model
{
	protected $fillable = ['id','join_time','enable'];
	
	public $incrementing = false;
	
	protected $keyType = 'string';
	
	public function metas(){
		return $this->hasMany(LineGroupMeta::Class);
	}
	
	public function server(){
		return $this->hasOne(Server::Class);
	}
	
	public function events(){
		return $this->hasMany(LineEvent::Class);
  }
  
  public function name_records(){
    return $this->morphMany(NameRecord::class,'recordable');
  }

  public function partners(){
    $middle_table = 'partner_group_pairs';
    $search_key = 'line_group_id';
    return $this->belongsToMany(
      Partner::class,
      $middle_table,
      $search_key
    );
  }

  public function users(){
    $middle_table = 'group_user_pairs';
    $search_key = 'line_group_id';
    $target_key = 'line_user_id';
    return $this->belongsToMany(
      LineUser::class,
      $middle_table,
      $search_key,
      $target_key
    );
  }
}
