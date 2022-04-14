<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Sales extends Model
{
	protected $fillable = ['line_user_id','sn'];
	
	public function user(){
		return $this->belongsTo(LineUser::Class, 'line_user_id');
	}
}
