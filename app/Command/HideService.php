<?php
namespace App\Command;

use App\Model\Server;
use App\Model\Service;

class HideService extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new HideService();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '藏方案',
			'cmd' => '藏方案',
			'description' => '隱藏方案，格式為:#隱藏{服務員名稱}{次數}/{分鐘}',
			'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Admin'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
	  $message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd'].'{服務員名稱}{次數}/{分鐘}{H或S}';
		if( strpos($args->command, $this->command_data['cmd'])!==0 )
			return $message;


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
    if ( mb_substr($command,-1)=='H' || mb_substr($command,-1)=='S' ){
      $hide_or_show = mb_substr($command,-1);
      $command = mb_substr($command,0,-1);
    }
		//過濾指令字
    $command_msg = substr($command, strlen($this->command_data['cmd']));
    if( preg_match('/[a-zA-Z]*\p{Han}*[a-zA-Z]*/u',$command_msg,$tmp) && !empty($command_msg)){
			// 搜尋到的漢字在指令最前頭
			if( strpos($command_msg,$tmp[0])===0 ){
				$name = $tmp[0];
			}
    }
    if (!empty($name)){
      $s_time_and_period = substr($command_msg,strlen($name),strlen($command_msg));
      $temp = [];
      $temp = explode("/",$s_time_and_period);
      if ( !isset($temp[1]) ){
        return $message;
      }
      if ( !in_array($temp[0],['1','2','3','4','5','6','n']) ){
        return $message.'且次數為1~6或者n';
      }
      if (!is_numeric($temp[1])){
        return $message.'且分鐘數須為數字';
      }
      else if($temp[1]==0){
        return $message.'且分鐘數不可為0';
      }
      $server = Server::where('name',$name)->whereIn('partner_id',$partner_id_array)->first();
      if (!$server){
        return '抱歉，未查找到指定服務員的資料';
      }
      else{
        $status = 'hide';
        if ( isset($hide_or_show) ){
          if ( $hide_or_show == 'H' ){
            $status = 'hide';
          }
          if ( $hide_or_show == 'S' ){
            $status = 'show';
          }
        }
        $service = Service::where('server_id',$server->id)->where('s_time',$temp[0])->where('period',$temp[1])->first();

        if (!$service){
          return '找不到想改狀態的方案';
        }
        $result = Service::where('server_id',$server->id)->where('s_time',$temp[0])->where('period',$temp[1])->update(['status'=>$status]);
        if ( $result ){
          return '該方案狀態已改為'.$status;
        }
        else{
          return '方案狀態更改失敗，請聯繫工程師';
        }
        
      }
    }
		return $message;
	}
	protected function SessionFunction( $args=null ) : string {
		
  }

}
