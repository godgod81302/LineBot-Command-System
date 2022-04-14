<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{	
	protected $guarded = [];
	public function server(){
		return $this->belongsTo(Server::Class);
	}

	// 業務成本
	public function salesCost(){
		$cost = 0;
		$cost += $this->server_fee;
		$cost += $this->broker_fee;
		$cost -= $this->company_cost;
		$cost += $this->company_profit;
		$cost -= $this->marketing_cost;
		return $cost;
	}
}
