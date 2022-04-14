<?php
namespace App\Command;

use App\Model\ServicePoint;
use App\Model\RoomData;
use App\Line\ApiHelper;

class SetRoomData extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new SetRoomData();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '&',
			'name' => '設房間',
			'cmd' => '設房間',
			'description' => '設房間資料,&設房間{據點一字暱稱+房號}(空格){據點所屬廠商id}{上/下}',
      'session_functions' => [
			],
			'access' => ['admin','group_admin'],
			'authorized_group_type' => ['Admin'],
		];
	}
		
	/* 實作 */
  protected function process( $args=null ) {

    $user = $args->user;
    $group = $args->group;
    $command = $args->command;
    if( !$group || $group->enble=='N' ){
      $message = "未授權";
      return config('app.debug') ? $message : null;
    }
    
    $message = "格式錯誤(E00),請使用以下格是:\n".
      $this->command_data['pre_command'].$this->command_data['cmd']."{據點一字暱稱+房號}(空格){據點所屬廠商id}{上/下}";
      
    $command = preg_replace('/\s+/',' ',$command);
    //去頭之後的內容
    $command = substr($command,strlen( $this->command_data['cmd']));
      
    $partner = $group->partners->first();
    if( !$partner )
      return "本群未綁定任何廠商";
    // if( $group->partners->count()>1 )
    //   return "本群綁定超過2個廠商";
    //去頭之後的內容

    $admin_access = false;
    $super_admin_acess = false;
    $partner_id_array = [];
		foreach( $user->group_admins as $admin ){
			$partner_id_array[] = $admin->partner->id;
			if( $admin->partner->id==$partner->id ){
				$admin_access = true;
				break;
			}
			else if ( $admin->partner->id == 1 ){
				$super_admin_acess = true;
				break;
			}
    }

    if ( !$admin_access && !$super_admin_acess ){
      return '您不具管理員身分，無法設照片';
    }
    $room_enable = 'Y';
    if ( mb_substr($command,-1)=='上' || mb_substr($command,-1)=='下' ){
      if ( mb_substr($command,-1) == '上'){
        $room_enable = 'Y';
      }
      else{
        $room_enable = 'N';
      }
      $command = mb_substr($command,0,-1);
    }
    $tmp = [];
    $tmp = explode(' ',$command);
    if (empty($tmp[0])){
      return $message;
    }
    if ( preg_match('/^\p{Han}/u',$tmp[0],$temp) ){
      $service_point_nickname = $temp[0];
      $room_number = mb_substr($command,1,mb_strlen($tmp[0])-1);
    }
    else{
      return $message;
    }
    if ( !isset($tmp[1]) ){
      $service_points = ServicePoint::where('nickname',$service_point_nickname)->whereIn('partner_id',$partner_id_array)->get();
      if (count($service_points)>1){
        return '有多個一字暱稱為'.$service_point_nickname.'的據點，請於廠商房間號後接上指定之廠商id，如:&設房照{據點暱稱+房號}(空格){所屬廠商id}';
      }
      if ( count($service_points)<1 ){
        return '抱歉，暱稱為:'.$service_point_name.'之據點不存在';
      }
      $service_point = $service_points->first();
    }
    else{
      if( !is_numeric($tmp[1])){
        return '廠商id需為數字!'.$this->command_data['description'];
      }
      else if( !in_array($tmp[1],$partner_id_array) ){
        return '抱歉，您不具有廠商id'.$tmp[1].'之管理員身份';
      }
      $service_point = ServicePoint::where('nickname',$service_point_nickname)->where('partner_id',$tmp[1])->first();
      if ( !$service_point ){
        return '抱歉，暱稱為:'.$service_point_name.'，且屬於廠商id為:'.$tmp[1].'之據點不存在';
      }
    }

    $update_result = RoomData::updateOrCreate(
      ['service_point_id'=>$service_point->id,'number'=>$room_number],
      ['enable'=>$room_enable]
    );
    if ( !$update_result ){
      return $service_point->name.'-'.$room_number.'房間更新失敗';
    }
    $message = '房間'.$service_point->name.'-'.$room_number.'啟用成功';
    if ( $room_enable =='N' ){
      $message = '房間'.$service_point->name.'-'.$room_number.'關閉成功';
    }
    
    $message = trim($message,"\n");


    return $message;
  }

  protected function SessionFunction( $args=null ){

  }

}
