<?php
namespace App\Command;

use App\Model\Partner;
use App\Model\ServerCreateData;


class CheckTempServerData extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new CheckTempServerData();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '查暫存服務員',
			'cmd' => '查暫存',
			'description' => '查暫存服務員',
			'args' => [
				'廠商ID','LineUserID','動作'
			],
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
        $server_create_data = ServerCreateData::where('name',$name)->where('partner_id',$partner_id)->first();

        if (!$server_create_data){
          return '抱歉，未查找到指定服務員的暫存資料';
        }
        else{
          $message = '';
          $temp_array = [];
          $temp_array = $server_create_data->toArray();
          $msg ='';
          foreach ( $temp_array as $key => $value){
            if (!empty($value)){
                if($key=='description'){
                  $msg .= 'special_tags:';
                  $arr = [];
                  $arr = json_decode($value,true);
                  foreach (  $arr['special_tags'] as $tag ){
                    $msg.= $tag.'.';
                  }
                  $msg .= "\n";
                }
                else{
                  $msg .= $key.':'.$value."\n";  
                }
                 
            }
          }
          return $msg;
        }
      }
      else{
        return '名字後須加上廠商數字id';
      }
    }
    else{
      $server_create_datas = ServerCreateData::whereIn('partner_id',$partner_id_array)->get();
      if (count($server_create_datas)==0){
        return '當前沒有暫存服務員';
      }
      $msg ='';
      foreach ( $server_create_datas as $server_create_data ){
        $temp_array = [];
        $temp_array = $server_create_data->toArray();

        foreach ( $temp_array as $key => $value){
          if (!empty($value)){
              if($key=='description'){
                $msg .= 'special_tags:';
                $arr = [];
                $arr = json_decode($value,true);
                foreach (  $arr['special_tags'] as $tag ){
                  $msg.= $tag.'.';
                }
                $msg .= "\n";
              }
              else{
                $msg .= $key.':'.$value."\n";  
              }
               
          }
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
