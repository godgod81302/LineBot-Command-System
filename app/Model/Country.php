<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
	protected $fillable = ['name','text_mark'];
	
	public function servers(){
		return $this->hasMany(Server::Class);
	}
}
