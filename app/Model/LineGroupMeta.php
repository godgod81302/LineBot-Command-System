<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class LineGroupMeta extends Model
{
	protected $fillable = ['line_group_id','name','value'];
	
	public function group(){
		$foreign_key = 'line_group_id';
		return $this->belongsTo(LineGroup::Class, $foreign_key);
	}
}
