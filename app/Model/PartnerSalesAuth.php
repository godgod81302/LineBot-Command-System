<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class PartnerSalesAuth extends Model
{
    // 設定資料表名稱
    protected $table = 'partner_sales_auth';
    public $timestamps = false;
    // 預設 primaryKey 為 id，如果不是的話需要另外設定
	public function partner_group_pairs(){
		return $this->belongsTo(PartnerGroupPair::class,'partner_id','partner_id');
	}
    //
}
