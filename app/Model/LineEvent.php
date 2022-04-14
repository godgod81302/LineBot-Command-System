<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class LineEvent extends Model
{
	protected $fillable = ['line_user_id','line_group_id','event'];
	
	public $timestamps = false;
	
	public function user(){
		$foreign_key = 'line_user_id';
		return $this->belongsTo(LineUser::Class, $foreign_key);
	}
	
	public function group(){
		$foreign_key = 'line_group_id';
		return $this->belongsTo(LineGroup::Class, $foreign_key);
	}
}
