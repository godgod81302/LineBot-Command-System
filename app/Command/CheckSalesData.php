<?php
namespace App\Command;

use App\Model\Partner;
use App\Model\PartnerSalesAuth;
use App\Model\Sales;
use App\Model\LineUser;

class CheckSalesData extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new CheckSalesData();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '查業務',
			'cmd' => '查業務',
			'description' => '查業務資料',
			'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Admin'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
	  $message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd'].'{業務群組代碼}-{廠商id}';
		if( strpos($args->command, $this->command_data['cmd'])!==0 )
			return $message;

		$message = "";
		$group = $args->group;
		if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return $message;
		}

		$command = $args->command;
		$user = $args->user;
		$partner_id_array = [];
		foreach( $user->group_admins as $group_admin){
			array_push($partner_id_array,$group_admin->partner_id);
		}
		//過濾指令字
    $command_msg = substr($command, strlen($this->command_data['cmd']));
    $temp = [];
    $temp = explode('-',$command_msg);
    $command_msg = $temp[0];
    if ( isset($temp[1]) ){
      $input_partner_id = $temp[1];
    }
    $temp = [];
    if( preg_match('/([0-9A-Za-z]+)$/',$command_msg,$temp) && !empty($command_msg)){
			// 搜尋到的漢字在指令最前頭
			if( strpos($command_msg,$temp[0])===0 ){
				$sales_group_code = $temp[0];
			}
    }
    if (!empty($sales_group_code)){
      if ( !empty($input_partner_id) ){
        if ( !is_numeric($input_partner_id) ){
          return '廠商id須為數字';
        }
        if (!in_array($input_partner_id,$partner_id_array)){
          return '您不具有'.$input_partner_id.'之廠商身分';
        }
        $partner_sales_auths = PartnerSalesAuth::where('sales_group_code','like',$sales_group_code.'%')->where('partner_id',$input_partner_id)->orderBy('sales_group_code', 'asc')->get();
        if (count($partner_sales_auths)==0){
          return '抱歉，未查找到相關的的業務資料';
        }
        else{
          $msg = '找到'.count($partner_sales_auths).'筆'."\n";
          foreach ( $partner_sales_auths as $partner_sales_auth ){
            $sales = Sales::where('id',$partner_sales_auth->sales_id)->first();
            $line_user = LineUser::where('id',$sales->line_user_id)->first();
            $msg .= $partner_sales_auth->sales_group_code.'.'.$line_user->latest_name."\n";
          }
          return $msg;
        }
      }
      else{
        //有整定群組id但沒輸入廠商id
        $partner_sales_auths = PartnerSalesAuth::where('sales_group_code','like',$sales_group_code.'%')->orderBy('sales_group_code', 'asc')->get();
        if (count($partner_sales_auths)==0){
          return '抱歉，未查找到相關的的業務資料';
        }
        else{
          $msg = '找到'.count($partner_sales_auths).'筆'."\n";
          foreach ( $partner_sales_auths as $partner_sales_auth ){
            $sales = Sales::where('id',$partner_sales_auth->sales_id)->first();
            $line_user = LineUser::where('id',$sales->line_user_id)->first();
            $msg .= $partner_sales_auth->sales_group_code.'.'.$line_user->latest_name."\n";
          }
          return $msg;
        }

      }
    }
    else{
      //查業務，目前沒有看該群組綁定的廠商id，而單只是已下令者的廠商id去抓，所以可能會發生，在廠商2的群組， 下令者同時是 1 2管理員，下令後， 1 2業務資料全印出
      $msg = '';
      foreach ( $partner_id_array as $partner_id ){
        $partner_sales_auths = PartnerSalesAuth::where('partner_id',$partner_id)->orderBy('sales_group_code', 'asc')->get();
        $msg .= '廠商'.$partner_id.'找到'.count($partner_sales_auths).'筆'."\n";
        foreach ( $partner_sales_auths as $partner_sales_auth ){
          $sales = Sales::where('id',$partner_sales_auth->sales_id)->first();
          $line_user = LineUser::where('id',$sales->line_user_id)->first();
          $msg .= $partner_sales_auth->sales_group_code.'.'.$line_user->latest_name."\n";
        }
        $msg .= '--------'."\n";
      }
      return $msg;
    }
    


		return $message;
	}
	protected function SessionFunction( $args=null ) : string {
		
  }

}
