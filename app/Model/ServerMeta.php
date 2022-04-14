<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ServerMeta extends Model
{
	public function server(){
		return $this->belongsTo(Server::Class);
	}
}
