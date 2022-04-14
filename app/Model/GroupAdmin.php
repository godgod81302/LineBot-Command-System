<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class GroupAdmin extends Model
{
	
	public function user(){
		$foreign_key = 'line_user_id';
		return $this->belongsTo(LineUser::Class, $foreign_key);
	}

	public function partner(){
		return $this->belongsTo(Partner::class,'partner_id');
	}
	public function partner_group_pairs(){
		return $this->belongsTo(PartnerGroupPair::class,'partner_id','partner_id');
	}
}
