<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
	
	public function metas(){
		return $this->hasMany(PartnerMeta::Class);
	}
}
