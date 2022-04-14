<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Broker extends Model
{
	protected $fillable = ['line_user_id','sn'];
	
	public function user(){
		$foreign_key = 'line_user_id';
		return $this->belongsTo(LineUser::Class, $foreign_key);
	}
	
	public function servers(){
		return $this->hasMany(Server::Class);
	}
}
