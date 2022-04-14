<?php
namespace App\Command;

use App\Model\Partner;
use App\Model\GroupAdmin;
use App\Model\LineUser;
use App\Model\Sales;
use App\Model\PartnerSalesAuth;
use Illuminate\Support\Facades\Redis;

class SetFakeSales extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new SetFakeSales();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '&',
			'name' => '設定假業務',
			'cmd' => '設假業務',
			'description' => '設定假業務，&設假業務(空格){業務群組代碼(三碼，限英數)}(空格){廠商ID}(空格){業務名稱}',
      'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Admin'],
      'reply_questions' => ['您可以到任何一個有機器人的群組tag您希望設為業務的帳號，但須注意該帳號不得已經是業務'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$user = $args->user;
		$group = $args->group;
		$command = $args->command;
	
		$message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."(空格){業務群組代碼(三碼，限英數)}(空格){廠商ID}(空格){業務名稱}',例如:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']." Sa7 2 劉德華";

    $commands = explode(' ',$command);

    if ( !isset($command[2]) ){
      $group_admin_datas = $user->group_admins;
      if ( count($group_admin_datas) >1 ){
        return '您擁有多個廠商之群組管理員身分，故無法省略廠商id資訊';
      }
      $partner_id = $group_admin_datas->first()->partner_id;
      $sales_group_code = $commands[1];
    }
    else{
      $partner_id = $commands[2];
      $sales_group_code = $commands[1];
    }

    $partner = Partner::find($partner_id);

		if( !$partner )
      return "查無編號#{$partner_id}的廠商";
       
		if( !preg_match('/^[A-Za-z0-9]{3}+$/',$sales_group_code) )
			return "業務群組代碼格式不正確";
    // if( substr($sales_group_code,-1) == 'e' )
    //   return "業務群組代碼結尾不可為e，因為e為系統保留字";
    $group_admin_datas = $user->group_admins;
    $has_partner_identify = false;
    foreach ( $group_admin_datas as $group_admin_data ){
      if ( $group_admin_data->partner_id == $partner_id ){
        $has_partner_identify = true;
      }
    }
    if ( substr($sales_group_code,0,1)=='t'){
      return 't開頭的業務群組代碼為系統所保留，請換一個';
    }
    if ( !$has_partner_identify ){
      return '您並非該廠商之群組管理員';
    }
    $sales_name = $commands[3];

    $partner_sales_auth_count = PartnerSalesAuth::where('sales_group_code','like',$sales_group_code.'%')->get();
    if ( count($partner_sales_auth_count)  > 9 ){
      return '業務群組不得超過9位業務，該業務群組目前已有'.count($partner_sales_auth_count).'位業務';
    }
    $index = count($partner_sales_auth_count);

    $if_fake_user_exist = LineUser::where('latest_name',$sales_name)->first();
    if ($if_fake_user_exist){
      return '已經有此名稱的臨時業務，請再次確認';
    }
    $fake_user = LineUser::where('id','like','%FAKE%')->get();

    $line_user = new LineUser();
    $line_user->id = 'FAKE'.str_pad(strval(count($fake_user)+1),27,'0',STR_PAD_LEFT);
    $line_user->latest_name = $sales_name;
    $result = $line_user->save();

    if ($result){
      $if_sales_exist = Sales::where('line_user_id',$line_user->id)->first();
      if ( !$if_sales_exist ){
        $sales = new Sales;
        $sales->line_user_id = $line_user->id;
        $sales->sn = 'S'.date('ymd').substr(time(),-5);
        $sales_save_result = $sales->save();
      }else{
        $sales_save_result = true;
        $sales = $if_sales_exist;
      }
    }
    else{
      return '業務假user資料新增失敗';
    }

    if ( $sales_save_result ){
      $if_partner_sales_exist = PartnerSalesAuth::where('sales_id',$sales->id)->where('partner_id',$partner_id)->first();
      if (!$if_partner_sales_exist){
        $partner_sales_auth = new PartnerSalesAuth;
        $partner_sales_auth->partner_id = $partner_id;
        $partner_sales_auth->sales_id = $sales->id;
        $partner_sales_auth->sales_group_code = $sales_group_code.$index;
        $partner_sales_auth_save_result = $partner_sales_auth->save();
      }
    }
    if ($partner_sales_auth_save_result){
      return '業務設定成功';
    }
		return $message;
  }
  //尚未檢查業務群裡面的人員
	protected function SessionFunction( $args=null ) : string {

  }
  


}
