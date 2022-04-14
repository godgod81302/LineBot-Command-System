<?php
namespace App\Command;

use App\Model\Partner;
use App\Model\Server;
use App\Model\Country;
use App\Model\Service;

class CheckServerWorkTime extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new CheckServerWorkTime();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '查上班',
			'cmd' => '查上班',
			'description' => '查服務員，格式為:#查上班{服務員名稱}',
			'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Admin'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
	  $message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd'].'{服務員名稱}';
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
    if( preg_match('/[a-zA-Z]*\p{Han}*[\x{3105}-\x{3129}]*[a-zA-Z]*/u',$command_msg,$tmp) && !empty($command_msg)){
			// 搜尋到的漢字在指令最前頭
			if( strpos($command_msg,$tmp[0])===0 ){
				$name = $tmp[0];
			}
    }
    if (!empty($name)){
      $partner_id = substr($command_msg,strlen($name),strlen($command_msg));
      if ( !empty($partner_id) ){
        if ( !is_numeric($partner_id) ){
          return '名字後須加上廠商數字id';
        }
        if (!in_array($partner_id,$partner_id_array)){
          return '您不具有'.$partner_id.'之廠商身分';
        }
        $server = Server::where('name',$name)->where('partner_id',$partner_id)->first();
        if (!$server){
          return '抱歉，未查找到指定服務員的資料';
        }
        else{
          $message = $this->serverData($server);
          return $message;
        }
      }
      else{
        $server = Server::where('name',$name)->whereIn('partner_id',$partner_id_array)->first();
        if (!$server){
          return '抱歉，未查找到指定服務員的資料';
        }
        else{
          $message = $this->serverData($server);
          return $message;
        }
      }
    }
    else{
      $servers = Server::whereIn('partner_id',$partner_id_array)->get();
      if (count($servers)==0){
        return '當前沒有服務員';
      }
      $message ='';
      foreach ( $servers as $server ){
        $message .= $this->serverData($server);
        $message .= '--------'."\n";
      }
      return $message;
    }
    


		return $message;
	}
	protected function SessionFunction( $args=null ) : string {
		
  }
  private function serverData($server){
    $message = '';
    $message .= 'name:'.$server->name."\n";
    $message .= 'partner_id:'.$server->partner_id."\n";
    $message .= '上班時間:'."\n".$server->start_time."\n";
    $message .= '下班時間:'."\n".$server->end_time."\n";
    $message .= '預設上班時間:'.$server->duty_start_time."\n";
    $message .= '預設下班時間:'.$server->duty_end_time."\n";
    return $message;
  }
}
