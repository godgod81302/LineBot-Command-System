<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ScheduleUnit extends Model
{
	public $timestamps = true;
	protected $guarded = [];
	public function server(){
		return $this->belongsTo(Server::Class);
	}
	
}
