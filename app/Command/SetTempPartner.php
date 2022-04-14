<?php
namespace App\Command;

use App\Model\Partner;
use App\Model\PartnerSalesAuth;
use App\Model\temp_group_admin;
use App\Model\GroupAdmin;
use App\Model\Sales;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
class SetTempPartner extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new SetTempPartner();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '&',
			'name' => '設定共派的臨時廠商及其管理員',
			'cmd' => '設共派',
			'description' => '設定共派的臨時廠商及其管理員',
      'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Server'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$user = $args->user;
		$group = $args->group;
		$command = $args->command;

    $message = '格式錯誤，此指令分為兩步驟'."\n".'1.先設臨時廠商，範例:'."\n".'&設共派-廠商'."\n".'name:XXX(廠商名)'."\n".'nickname:x(廠商名的一字暱稱)'."\n".
    '2.設共派管理員，範例:'."\n".'&設共派@某某人'."\n".'partner_id:X(上面設廠商回復的id數值)'."\n".'nickname:X(這裡填入作為管理員暱稱的一字中文代號)';

    $group = $args->group;
		if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return $message;
		}
		//過濾指令字
		$command_msg = mb_substr($command, mb_strlen($this->command_data['cmd']));

    $admin_access = false; 
    $super_admin_acess = false;
    $is_regular_partner = false;
    $group_partners = $group->partners;
		if( count($group_partners)==0 )
		  return "本群未綁定任何廠商";
    $group_partner_id_array = [];
    foreach ( $group_partners as $group_partner ){
      $group_partner_id_array[] = $group_partner->id;
    }
    $partner_id_array = [];
		foreach( $user->group_admins as $admin ){
      $partner_id_array[] = $admin->partner->id;
      $partner = Partner::where('id',$admin->partner->id)->where('status','regular')->first();
      if ($partner){
        $is_regular_partner = true;
      }
			if( in_array($admin->partner->id,$group_partner_id_array) ){
				$admin_access = true;
			}
      else if ( $admin->partner->id == 1 ){
        $super_admin_acess = true;
      }
		}
    if (!$is_regular_partner){
      $error_msg = '臨時共派管理員不可使用此指令拉管理員，違法操作已記錄並通知系統管理員';
      $log = Log::channel('ileagal-call');
      $log->debug(['user_id'=>$user->id,'msg'=>$error_msg,'command'=>$command]);
      return $error_msg;
    }

    $command_msg = str_replace(' ','',$command_msg);
    $break_line_list = [];
		$break_line_list = explode("\n",$command_msg);
		$other_command = strstr($break_line_list[0],'-');

    if ($other_command == '-廠商'){
      if(empty($break_line_list[1]) || empty($break_line_list[2])){
        return $message;
      }
      $partner_name = substr($break_line_list[1],-(strlen($break_line_list[1])-5));
      $partner_nickname = substr($break_line_list[2],-(strlen($break_line_list[2])-9));
      $if_partner_name_exist = Partner::where('name',$partner_name)->first();
      $if_partner_nickname_exist = Partner::where('nickname',$partner_nickname)->first();

      if ($if_partner_name_exist){
        return '第二行廠商名稱已經存在，請換一個!';
      }
      if ($if_partner_nickname_exist){
        return '第三行廠商暱稱已存在，請換一個!';
      }

      if (!preg_match("/^([\x7f-\xff]+)$/", $partner_name)){
        return '第二行之name請輸入漢字';
      }

      if (!preg_match("/^([\x7f-\xff]+)$/", $partner_nickname) || (mb_strlen($partner_nickname)!=1)){
        return '第三行之nickname請輸入單一字漢字';
      }

      $temp_partner = new Partner;
      $temp_partner->name = $partner_name;
      $temp_partner->nickname = $partner_nickname;
      $temp_partner->status = 'temp';
      $result = $temp_partner->save();
      if (!$result){
        return '共派廠商新增失敗，請聯繫工程師';
      }
      else{
        return '共派廠商新增成功，廠商id為'.$temp_partner->id;
      }
    }
    else if ($other_command == false){
      if(!empty($break_line_list[0])){
        if (!isset($args->mention)){
          return '請tag管理員line帳號!';
        }
        $mentions = $args->mention;
        if ( count($mentions->mentionees) != 1 ){
          return 'tag超過一個帳號，請再次確認';
        }
        if (!isset($mentions->mentionees[0]->userId)){
          return 'tag的帳號沒有設定個人line id，無法設為服務員line帳號';
        }
        if(!isset($break_line_list[1])){
          return $message;
        }
        $input_partner_id = substr($break_line_list[1],-(strlen($break_line_list[1])-11));
        if (!is_numeric($input_partner_id)){
          return '第二行partner_id請輸入數字';
        }
        $partner = Partner::where('id',$input_partner_id)->where('status','temp')->first();
        if (!$partner){
          return '找不到輸入的廠商';
        }
        if (!is_numeric($input_partner_id)){
          return '第二行partner_id請輸入數字';
        }
        $nickname = substr($break_line_list[2],-(strlen($break_line_list[2])-9));

        if (!preg_match("/^([\x7f-\xff]+)$/", $nickname) || (mb_strlen($nickname)!=1)){
          return '第三行之nickname請輸入單一字漢字';
        }

        $is_admin_nickname_exist = GroupAdmin::where('nickname',$nickname)->first();
        $is_temp_admin_nickname_exist = temp_group_admin::where('nickname',$nickname)->first();

        if ( ($is_admin_nickname_exist!=false) || ($is_temp_admin_nickname_exist!=false) ){
          return '該管理員暱稱已被占用，請換一個字喔';
        }
        $is_already_temp_admin = temp_group_admin::where('line_user_id',$mentions->mentionees[0]->userId)->first();
        if ($is_already_temp_admin){
          return '該帳號已為共派管理員，無須再次設定';
        }
        $partner_sales_auth_count = PartnerSalesAuth::where('sales_group_code','like','t'.'%')->get();
        if (count($partner_sales_auth_count)==950){
          return '臨時業務系統人數額滿，請盡速通知系統工程師排解此狀況';
        }

        $sales_group_code = 't'.sprintf("%03d",count($partner_sales_auth_count));
        //先不分什麼業務群組
        // if ( count($partner_sales_auth_count) + count($mentionees) > 10 ){
        //   return '業務群組不得超過9位業務，該業務群組目前已有'.count($partner_sales_auth_count).'位業務，當前欲設定'.count($mentionees).'位';
        // }
        //突然考慮到如果廠商另外建立，那豈不是連服務員也要複製了，因此需要改變作法，直接把臨時共派管理員記錄到同一個廠商下
        $temp_group_admin = new temp_group_admin;
        $temp_group_admin->line_user_id = $mentions->mentionees[0]->userId;
        $temp_group_admin->line_group_id = $group->id;
        $temp_group_admin->partner_id = $partner->id;
        $temp_group_admin->nickname = $nickname;
        $result = $temp_group_admin->save();
        if ($result){
          $if_sales_exist = Sales::where('line_user_id',$mentions->mentionees[0]->userId)->first();
          if ( !$if_sales_exist ){
            $sales = new Sales;
            $sales->line_user_id = $mentions->mentionees[0]->userId;
            $sales->sn = 'S'.date('ymd').substr(time(),-5);
            $sales_save_result = $sales->save();
          }else{
            $sales_save_result = true;
            $sales = $if_sales_exist;
          }

          if ( $sales_save_result ){
            $partner_sales_auth = new PartnerSalesAuth;
            $partner_sales_auth->partner_id = $partner->id;
            $partner_sales_auth->sales_id = $sales->id;
            $partner_sales_auth->sales_group_code =  $sales_group_code;
            $partner_sales_auth_save_result = $partner_sales_auth->save();
            if (!$partner_sales_auth_save_result){
              $line_user = LineUser::where('id',$mentions->mentionees[0]->userId)->first();
              return '新增業務代碼'.$line_user->latest_name.'時發生錯誤，新增程序已中斷';
            }
            else{
              return '新增共派管理員成功，同時給定業務代碼'.$sales_group_code;
            }
          }
          else{
            $line_user = LineUser::where('id',$mentions->mentionees[0]->userId)->first();
            return '新增業務'.$line_user->latest_name.'時發生錯誤，新增程序已中斷';
          }
        }
      }
    }
		return $message;
  }
  //尚未檢查業務群裡面的人員
	protected function SessionFunction( $args=null ) : string {

  }

}
