<?php
namespace App\Command;

use App\Model\Server;
use App\Model\Area;
use Illuminate\Support\Facades\Redis;

class EnableServer extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new EnableServer();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '&',
			'name' => '設定服務員上班時間',
			'cmd' => '啟用服務員',
			'description' => '啟用服務員，格式為:&啟用服務員{服務員名稱}'."\n".'&啟用服務員花花',
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
    $this->command_data['pre_command'].$this->command_data['cmd']."{服務員名稱}"."\n"."&啟用服務員花花";
		//過濾指令字
		$command_msg = mb_substr($command, mb_strlen($this->command_data['cmd']));
    if ( empty($command_msg) ){
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
        $command_msg = substr($command_msg,strlen($match[0]),strlen($command_msg));
        $server_name = $command_msg;

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
        $server_name = $command_msg;
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
            return '查無名稱為'.$server_name.'之服務員';
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
  
    // $area_name = Area::where('id',$server->area_id)->first()->name;
    // if ($area_name=='下架'){
    //   return '服務員仍於下架據點，請先使用改服務改據點後再嘗試啟用服務員';
    // }
    
    $result = Server::where('id',$server->id)->update(['enable'=>'Y']);
    if ($result){
      CommandUtil::scheduleUnitSeeds(date('Y-m-d 07:i:s',strtotime('-1 day')),$server->id);
      $message = '服務員'.$server->name.'已經啟用';
      return $message;
    }
    else{
      return '服務員'.$server->name.'啟用失敗';
    }

		return $message;
  }
  //尚未檢查業務群裡面的人員
	protected function SessionFunction( $args=null ) : string {

  }
  


}
