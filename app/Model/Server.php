<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
	protected $guarded = [];

	public function group(){
		$foreign_key = 'line_group_id';
		return $this->belongsTo(LineGroup::Class, $foreign_key);
	}
	
	public function partner(){
		return $this->belongsTo(Partner::Class);
	}
	
	public function metas(){
		return $this->hasMany(ServerMeta::Class);
	}
	
	public function broker(){
		return $this->belongsTo(Broker::Class);
  }
  
  public function user(){
    return $this->belongsTo(LineUser::class,'line_user_id');
  }
	
	public function services(){
		return $this->hasMany(Service::Class);
	}
	
	public function schedule_units(){
		return $this->hasMany(ScheduleUnit::Class);
	}



}
