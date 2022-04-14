<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
	public $timestamps = true;
	protected $guarded = [];
	
	public function user(){
		$foreign_key = 'line_user_id';
		return $this->belongsTo(LineUser::Class, $foreign_key);
	}
	
	public function partner(){
		return $this->belongsTo(Partner::Class);
	}
	
	public function server(){
		return $this->belongsTo(Server::Class);
	}
}
