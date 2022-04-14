<?php
namespace App\Command;

use App\Model\Server;

use Illuminate\Support\Facades\Redis;

class ChangeServerWorkTime extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new ChangeServerWorkTime();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '&',
			'name' => '改服務員單日上班時間',
			'cmd' => '改上班',
			'description' => '改服務員平常之上下班時間，格式為:&設上班'."{廠商代號，若沒有重複服務員可以不填入}{服務員名稱}{ ( }{上班時間月日時分}{ ~ }{下班時間月日時分}"."\n"."&改上班花花(05/25-1930~0200",
      'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Admin','Server'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$user = $args->user;
		$group = $args->group;
		$command = $args->command;
    $group = $args->group;
		if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return $message;
		}
    $message = "格式錯誤(E00),請使用以下格是:\n".
    $this->command_data['pre_command'].$this->command_data['cmd']."{廠商代號，若沒有重複服務員可以不填入}{服務員名稱}{ ( }{上班時間月日時分}{ ~ }{下班時間月日時分}"."\n"."&改上班花花(05/25-1930~0200";
		//過濾指令字
		$command_msg = mb_substr($command, mb_strlen($this->command_data['cmd']));
    if ( !strpos($command_msg,'(') ){
      return $message;
    }
    if ( !strpos($command_msg,'-') ){
      return $message;
    }
    if ( !strpos($command_msg,'/') ){
      return $message;
    }
    $admin_access = false; 
    $super_admin_acess = false;

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
			if( in_array($admin->partner->id,$group_partner_id_array) ){
				$admin_access = true;
				break;
			}
      else if ( $admin->partner->id == 1 ){
        $super_admin_acess = true;
				break;
      }
		}

    if ( $admin_access || $super_admin_acess ){
      //管理員代訂
      $match = [];
      if( preg_match('/^[0-9]+/', $command_msg, $match) ){
        $command_msg = substr($command_msg,strlen($match[0])-1,strlen($command_msg));
        $temp = [];
        $temp = explode('(',$command_msg);
        $server_name = $temp[0];
        if (!isset($temp[1])){
          return $message;
        }

        //理論上temp[1]裡面是裝上班時間~下班時間
        $command_msg = $temp[1];

        $partner_id = $match[0];
        $server = Server::where('name',$server_name)->where('partner_id',$partner_id)->first();
        if (!$super_admin_acess){ 
          $partner_id_array = [];
          foreach ( $user->group_admins as $group_admin ){
            $partner_id_array[] = $group_admin->partner_id;
          }
          if (!in_array($partner_id,$partner_id_array)){
            return  '您不具有廠商id:'.$partner_id.'之管理員身分';
          }
        }
        if (!$server){
          return '廠商id:'.$partner_id.'找不到指定服務員'.$server_name;
        }
      }
      else{
        $temp = [];
        $temp = explode('(',$command_msg);
        $server_name = $temp[0];
        if (!isset($temp[1])){
          echo 1447;
          return $message;
        }
        //理論上temp[1]裡面是裝上班時間~下班時間
        $command_msg = $temp[1];
        $servers = Server::where('name',$server_name)->get();
        if (count($servers)>1){
          $message = '由於未提供廠商代號，且名稱為:'.$server_name.'的服務員不只一名，請查明後重新輸入';
          if ($super_admin_acess){
            $message = '查詢到不只一名服務員，請明確指定廠商代號及服務員'."\n";
            foreach( $servers as $server ){
             $message .= $server->name.'-'.$server->partner_id."\n";
            }
            echo 777;
          return $message;
          }
        }
        if (count($servers)==0){
          if (!empty($server_name)){
            return '查無名稱為'.$server_name.'的服務員';
          }
        }
        //到這表示該名稱服務員只有一個
        if (empty($server_name)){
          return '未輸入服務員名稱,'.$message;
        }
        else{
          $server = Server::where('name',$server_name)->first();
          if (!$server){
            return '查無名稱為'.$command_msg.'之服務員';
          }
        }

        $partner_id = $server->partner_id;
        if (!$super_admin_acess){
          $partner_id_array = [];
          foreach ( $user->group_admins as $group_admin ){
            $partner_id_array[] = $group_admin->partner_id;
          }
          if (!in_array($partner_id,$partner_id_array)){
            return  '您不具有該服務員所屬之廠商id:'.$partner_id.'之管理員身分';
          }
        }

      }

    }
    else{
      return '您不具備廠商管理員之權限';
    }

    $input_date = substr($command_msg,0,5);
    $input_month = substr($input_date,0,2);
    $input_day = substr($input_date,3,2);
    $input_date = date('Y').'-'.$input_month.'-'.$input_day;
    if (!checkdate($input_month,$input_day,date('Y')) ){
      return '輸入之日期時段不合法';
    }

    $tmp = [];
    $tmp = explode('~',substr($command_msg,6,9));
    if ( !isset($tmp[1]) ){
      return '請輸入開始及結束時間'.$message;
    }
    if ( isset($tmp[0]) && !empty($tmp[0]) ){
      $hour = substr($tmp[0],0,2);
      $minutes = substr($tmp[0],2,2);
      if ($hour < 0 || $hour > 24 || !is_numeric($hour) || $minutes < 0 || $minutes > 59 || !is_numeric($minutes) ){
        return '開始時段之時間不合法';
      }
    }
    if ( isset($tmp[1]) && !empty($tmp[1]) ){
      $hour2 = substr($tmp[1],0,2);
      $minutes2 = substr($tmp[1],2,2);
      if ($hour2 < 0 || $hour2 > 24 || !is_numeric($hour2) || $minutes2 < 0 || $minutes2 > 59 || !is_numeric($minutes2) ){
        return  '結束時段之時間不合法';
      }
    }
    // < config('app.system.day_split_hour') 
    if ( $hour < config('app.system.day_split_hour') ){
      $start_time = date('Y-m-d',strtotime($input_date)+86400).' '.$hour.':'.$minutes.':00';
    }
    else{
      $start_time = $input_date.' '.$hour.':'.$minutes.':00';
      if ($hour==24){
        $start_time = date('Y-m-d',strtotime($input_date)+86400).' 00:'.$minutes.':00';
      }
    }

    if ( $hour2 < config('app.system.day_split_hour') ){
      $end_time = date('Y-m-d',strtotime($input_date)+86400).' '.$hour2.':'.$minutes2.':00';
    }
    else{
      $end_time = $input_date.' '.$hour2.':'.$minutes2.':00';
      if ($hour2==24){
        $end_time = date('Y-m-d',strtotime($input_date)+86400).' 00:'.$minutes2.':00';
      }
    }

    if ( strtotime($end_time)<strtotime($start_time) ){
      return '結束時間不得早於等於開始時間';
    }

    $update_array = [];
    $update_array['start_time'] = $start_time;
    $update_array['end_time'] = $end_time;
    $result = Server::where('id',$server->id)->update($update_array);
    if ($result){
      $message = '服務員'.$server->name;
      $reply_start_time = $update_array['start_time'];
      $reply_end_time = $update_array['end_time'];
      if (substr($reply_start_time,11,2)=='00'){
        $reply_start_time = date('Y-m-d',strtotime($reply_start_time)-86400).' 24:'.date('i:s',strtotime($reply_start_time));
      }
      if (substr($reply_end_time,11,2)=='00'){
        $reply_end_time = date('Y-m-d',strtotime($reply_end_time)-86400).' 24:'.date('i:s',strtotime($reply_end_time));
      }
      $message .= '上班時間更新為:'."\n".$update_array['start_time'];
      $message .= "\n";
      $message .= '下班時間更新為:'."\n".$update_array['end_time'];
      CommandUtil::scheduleUnitSeeds($reply_start_time,$server->id);
      return $message;
    }
    else{
      return '服務員'.$server->name.'上班時段更新失敗';
    }

		return $message;
  }
  //尚未檢查業務群裡面的人員
	protected function SessionFunction( $args=null ) : string {

  }
  


}
