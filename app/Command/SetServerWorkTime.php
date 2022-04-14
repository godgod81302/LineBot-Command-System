<?php
namespace App\Command;

use App\Model\Server;

use Illuminate\Support\Facades\Redis;

class SetServerWorkTime extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new SetServerWorkTime();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '&',
			'name' => '設定服務員上班時間',
			'cmd' => '設上班',
			'description' => '設定服務員平常之上下班時間，格式為:&設上班{服務員名稱}{ ( }{上班時間}{ ~ }{下班時間}'."\n".'&設上班花花(21:00~03:00',
      'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Admin'],
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
    $this->command_data['pre_command'].$this->command_data['cmd']."{廠商代號，若沒有重複服務員可以不填入}{服務員名稱}{ ( }{上班時間}{ ~ }{下班時間}"."\n"."&設上班花花(21:00~03:00";
		//過濾指令字
		$command_msg = mb_substr($command, mb_strlen($this->command_data['cmd']));
    if ( !strpos($command_msg,'(') ){
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
      // $work_start_time = date('Y-m-d 07:i:s');
    }
    else{
      return '您不具備廠商管理員之權限';
    }
    $tmp = [];
    $tmp = explode('~',$command_msg);
    if ( isset($tmp[0]) && !empty($tmp[0]) ){
      $hour = substr($tmp[0],0,2);
      $minutes = substr($tmp[0],3,2);
      if ($hour < 0 || $hour > 24 || !is_numeric($hour) || $minutes < 0 || $minutes > 59 || !is_numeric($minutes) ){
        return '開始時段之時間不合法';
      }
    }
    if ( isset($tmp[1]) && !empty($tmp[1]) ){
      $hour2 = substr($tmp[1],0,2);
      $minutes2 = substr($tmp[1],3,2);
      if ($hour2 < 0 || $hour2 > 24 || !is_numeric($hour2) || $minutes2 < 0 || $minutes2 > 59 || !is_numeric($minutes2) ){
        return  '結束時段之時間不合法';
      }
    }
    if ( ($hour<config('app.system.day_split_hour')) &&  ($hour2<config('app.system.day_split_hour')) ){
      if ( $hour > $hour2 ){
        return '若為午夜後，開始時間需大於結束時間';
      }
    }
    $update_array = [];
    if (isset($tmp[0])&&!empty($tmp[0])){
      if ( substr($tmp[0],0,2)==24 ){
        $tmp[0] = '00'.substr($tmp[0],2,3);
      }
      $update_array['duty_start_time'] = $tmp[0];
    }
    if (isset($tmp[1])&&!empty($tmp[1])){
      if ( substr($tmp[1],0,2)==24 ){
        $tmp[1] = '00'.substr($tmp[1],2,3);
      }
      $update_array['duty_end_time'] = $tmp[1];
    }
    
    $result = Server::where('id',$server->id)->update($update_array);
    if ($result){
      $message = '服務員'.$server->name;
      if (isset($update_array['duty_start_time'])){
        if (substr($update_array['duty_start_time'],0,2)=='00'){
          $update_array['duty_start_time'] = '24:'.substr($update_array['duty_start_time'],3,2);
        }
        $message .= '預設上班時間更新為:'.$update_array['duty_start_time'];
      }
      if (isset($update_array['duty_end_time'])){
        if (isset($update_array['duty_start_time'])){
          $message .= "\n";
        }
        if (substr($update_array['duty_end_time'],0,2)=='00'){
          $update_array['duty_end_time'] = '24:'.substr($update_array['duty_end_time'],3,2);
        }
        $message .= '預設下班時間更新為:'.$update_array['duty_end_time'];
      }
      return $message;
    }
    else{
      return '服務員'.$server->name.'預設時段更新失敗';
    }

		return $message;
  }
  //尚未檢查業務群裡面的人員
	protected function SessionFunction( $args=null ) : string {

  }
  


}
