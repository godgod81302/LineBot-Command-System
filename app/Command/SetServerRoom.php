<?php
namespace App\Command;

use App\Model\Server;
use App\Line\ApiHelper;
use App\Model\ServicePoint;
use App\Model\RoomData;
use App\Model\RoomServerPair;
use Illuminate\Support\Facades\Redis;
use Illuminate\Cache\RedisLock;
use Illuminate\Support\Facades\Storage;
class SetServerRoom extends BaseCommand{
  
  private static $instance;
  
  public static function getInstance(){
    if( !self::$instance ){
      self::$instance = new SetServerRoom();
    }
    return self::$instance;
  }
  
  private function __construct(){
    $this->command_data = [
      'pre_command' => '&',
      'name' => '綁房間',
      'cmd' => '綁房間',
      'description' => '綁定服務員房間，例如:&綁房間{服務員名}(空格){據點}(空格){廠商id}(空格){房號}',
      'session_functions' => [
        'setImg',
      ],
      'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Admin'],
      'reply_questions' => [],
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
    //可以的話先return200，避免超時 上線前加上

    $message = "格式錯誤(E00),請使用以下格是:\n".
      $this->command_data['pre_command'].$this->command_data['cmd']."{服務員名}(空格){據點名稱}(空格){廠商id}(空格){房號}";
      
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
    $is_force = false;
    if ( mb_substr($command,-1)=='*' ){
      $is_force = true;
      $command=mb_substr($command,0,-1);
    }
    $tmp =[];
    $tmp = explode(' ',$command);

    $is_server_exist = Server::where('name',$tmp[0])->whereIn('partner_id',$partner_id_array)->first();
    if ( !$is_server_exist ){
      return '查無目標服務員，請再次確認(暫存服務員尚無法上傳圖片)';
    }

    $is_server_has_a_room = RoomServerPair::where('server_id',$is_server_exist->id)->first();
    // if ( $is_server_has_a_room ){
    //   if ( !$is_force ){
    //     $room_data = RoomData::where('id',$is_server_has_a_room->room_data_id)->first();
    //     return '服務員:'.$tmp[0].'已經綁定房號'.$room_data->number.'，若要改綁，請再結尾加上*表示強制，而前綁定將解除';
    //   }
    //   else{
    //     RoomServerPair::where('server_id',$is_server_exist->id)->delete();
    //   }
    // }

    if ( !isset($tmp[1]) ||  !isset($tmp[2]) ||  !isset($tmp[3]) ){
      return $message;
    }
    
    if ( !isset($tmp[2]) || empty($tmp[2]) ){
      return '請輸入據點所屬廠商id'.$this->command_data['description'];
    }
    else if( !is_numeric($tmp[2])){
      return '廠商id需為數字!'.$this->command_data['description'];
    }
    else if( !in_array($tmp[2],$partner_id_array) ){
      return '抱歉，您不具有廠商id'.$tmp[2].'之管理員身份';
    }
    $service_point_partner_id = $tmp[2];
    
    $is_service_point_exist = ServicePoint::where('name',$tmp[1])->where('partner_id',$service_point_partner_id)->first();
    if (!$is_service_point_exist){
      if ( $tmp[1]=='下架' ){
        $is_service_point_exist = ServicePoint::where('name',$tmp[1])->first();
      }
      else{
        return '據點'.$tmp[1].'不存在';
      }
    }
    
    $is_room_exist = RoomData::where('service_point_id',$is_service_point_exist->id)->where('number',$tmp[3])->first();
    if ( !$is_room_exist ){
      return '據點:'.$tmp[1].'下之房間'.$tmp[3].'不存在';
    }
    if ( $is_room_exist->enable=='N' ){
      return '房間'.$tmp[3].'未開放';
    }

    if ($is_service_point_exist->id == 999999){
      $is_server_exist->enable = 'N';
      $is_server_exist->save();
    }
    else{
      $is_server_exist->enable = 'Y';
      $is_server_exist->save();
    }
    $room_server_pair = RoomServerPair::where('room_data_id',$is_room_exist->id)->first();
    if ( $room_server_pair ){

      // if ( $is_force ){
        // if ( !$super_admin_acess ){
        //   $pre_server_partner_id = Server::where('id',$room_server_pair->server_id)->first()->partner_id;
        //   if ( !in_array($pre_server_partner_id,$partner_id_array) ){
        //     return '該房間已有服務員，且您不具備該服務員之管理權，如仍須更動，請聯繫系統工程師';
        //   }
          
        // }
        $is_server_pair_target_room = RoomServerPair::where('room_data_id',$is_room_exist->id)->where('server_id',$is_server_exist->id)->first();
        if ( !$is_server_pair_target_room ){
          RoomServerPair::where('server_id',$is_server_exist->id)->delete();
          $room_server = new RoomServerPair;
          $room_server->server_id = $is_server_exist->id;
          $room_server->room_data_id = $is_room_exist->id;
          $result = $room_server->save();
          if ($result){
            $pre_server_name = Server::where('id',$room_server_pair->server_id)->first()->name;
            if ( $tmp[1] != '下架' ){
              return '提醒!房號'.$is_service_point_exist->nickname.$tmp[3].'，之服務員已有:'.$pre_server_name.'，同時也已綁定服務員:'.$tmp[0];
            }
            else{
              return '服務員已下架';
            }

          }
        }
        else{
          return '該服務員本已綁定目標房間，無須再次綁定';
        }
      // }
      // else{
      //   return '房間已由其他服務員綁定，若要改綁，請再結尾加上*表示強制，而前綁定將解除';
      // }

    }
    else{
      RoomServerPair::where('server_id',$is_server_exist->id)->delete();
      $room_server = new RoomServerPair;
      $room_server->server_id = $is_server_exist->id;
      $room_server->room_data_id = $is_room_exist->id;
      $result = $room_server->save();
      if ($result){
        return '房號'.$tmp[3].'，已綁定為'.$tmp[0];
      }
    }



    $message = trim($message,"\n");

    return $message;
  }
  protected function SessionFunction( $args=null ){
  }
}
