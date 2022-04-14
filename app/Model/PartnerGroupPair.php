<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class PartnerGroupPair extends Model
{
	public $timestamps = false;
	protected $fillable = ['partner_id','line_group_id','group_type'];
	//
	public function group(){
		return $this->belongsTo(LineGroup::class,'line_group_id');
	}

	public function partner(){  
		return $this->belongsTo(Partner::class);
	}
}
