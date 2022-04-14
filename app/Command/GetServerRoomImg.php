<?php
namespace App\Command;

use App\Model\Server;
use App\Line\ApiHelper;
use App\Model\Image;
use App\Model\RoomServerPair;
use App\Model\RoomImgPair;
use App\Model\RoomData;
use App\Model\ServicePointImgPair;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
class GetServerRoomImg extends BaseCommand{
  
  private static $instance;
  
  public static function getInstance(){
    if( !self::$instance ){
      self::$instance = new GetServerRoomImg();
    }
    return self::$instance;
  }
  
  private function __construct(){
    $this->command_data = [
      'pre_command' => '#',
      'name' => '取得服務員房間照片',
      'cmd' => '房照',
      'description' => '取得服務員房間照片，#房照{服務員名}(空格){查檢查點照填1，查房間照填2}，例如: #房照花花 1',
      'session_functions' => [
        'setImg',
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
      $this->command_data['pre_command'].$this->command_data['cmd']."{服務員名}(空格){查檢查點照填1，查房間照填2}";
      
    $command = preg_replace('/\s+/',' ',$command);
    //去頭之後的內容
    $command = substr($command,strlen( $this->command_data['cmd']));
    $tmp = [];
    $tmp = explode(' ',$command);
    $command = $tmp[0];
    if (!isset($tmp[1])){
      return $message;
    }
    if ( ($tmp[1]!=1) && ($tmp[1]!=2) ){
      return '空格後接的需為1或2，'.$this->command_data['description'];
    }
    if ($tmp[1]==1){
      $img_for = 'checkpoint';
    }
    else{
      $img_for = 'room';
    }
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

    $is_server_exist = Server::where('name',$command)->whereIn('partner_id',$partner_id_array)->first();
    if ( !$is_server_exist ){
      return '查無目標服務員，請再次確認(暫存服務員尚無法上傳圖片)';
    }
    $room_server_pair = RoomServerPair::where('server_id',$is_server_exist->id)->first();
    if ( !$room_server_pair ){
      return '該服務員仍未綁定房間，無法查詢房照';
    }
    $messages = [];
    $room_data = RoomData::where('id',$room_server_pair->room_data_id)->first();
    // $service_point_img_pairs = ServicePointImgPair::where('service_point_id',$room_data->service_point_id)->offset(0)->limit(2)->get();

    // foreach (  $service_point_img_pairs as  $service_point_img_pair ){
    //   $object = (object)[];
    //   $object->type = 'image';
    //   $image_url = Image::where("id",$service_point_img_pair->image_id)->first()->image_url;
    //   $image_data = [];
    //   $image_data = explode("/",$image_url);
    //   $url = Storage::disk($image_data[0])->url($image_data[1]);
    //   $object->originalContentUrl = $url;
    //   $object->previewImageUrl = $url;
    //   $messages[] = $object;
    // }
    $room_img_pairs = RoomImgPair::where("room_data_id",$room_server_pair->room_data_id)->where('img_for',$img_for)->offset(0)->limit(5)->get();
    foreach (  $room_img_pairs as  $room_img_pair ){
      $object = (object)[];
      $object->type = 'image';
      $image_url = Image::where("id",$room_img_pair->image_id)->first()->image_url;
      $image_data = [];
      $image_data = explode("/",$image_url);
      $url = Storage::disk($image_data[0])->url($image_data[1]);
      $object->originalContentUrl = $url;
      $object->previewImageUrl = $url;
      $messages[] = $object;
    }


    return $messages;
  }
  protected function SessionFunction( $args=null ){

  }



}
