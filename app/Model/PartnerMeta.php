<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class PartnerMeta extends Model
{
	
	public function partner(){
		return $this->belongsTo(Partner::Class);
	}
}
