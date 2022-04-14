<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class LineUserMeta extends Model
{
	//
	public function user(){
		$foreign_key = 'line_user_id';
		return $this->belongsTo(LineUser::Class, $foreign_key);
	}
}
