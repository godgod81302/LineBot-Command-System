<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class LineUser extends Model
{
	public $incrementing = false;
	
	protected $keyType = 'string';
	
	protected $fillable = ['id','status','latest_name','latest_img_url'];
	
	public function metas(){
		// hasMany( $class_name, $child_model_foreign_key, $key_in_this_mndel )
		// 		$child_model_foreign_key default value in here is 'line_user_id'
		// 			which conbined with LineUser's table name and '_id'
		// 		$key_in_this_mndel default value is 'id' in LineUser's table,
		return $this->hasMany(LineUserMeta::Class);
	}
	
	public function events(){
		return $this->hasMany(LineEvent::Class);
	}
	
	public function sales(){
		return $this->hasOne(Sales::Class);
	}
	
	public function group_admins(){
		return $this->hasMany(GroupAdmin::Class);
	}
	
	public function broker(){
		return $this->hasOne(Broker::Class);
  }

  public function server(){
    return $this->hasOne(Server::class);
  }
  
  public function name_records(){
    return $this->morphMany(NameRecord::class,'recordable');
  }

  // user所在的group有哪些
  public function groups(){
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
