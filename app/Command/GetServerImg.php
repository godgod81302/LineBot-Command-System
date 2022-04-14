<?php
namespace App\Command;

use App\Model\Server;
use App\Line\ApiHelper;
use App\Model\Image;
use App\Model\ServerImgPair;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
class GetServerImg extends BaseCommand{
  
  private static $instance;
  
  public static function getInstance(){
    if( !self::$instance ){
      self::$instance = new GetServerImg();
    }
    return self::$instance;
  }
  
  private function __construct(){
    $this->command_data = [
      'pre_command' => '#',
      'name' => '取得服務員照片',
      'cmd' => '照片',
      'description' => '取得服務員照片，例如: #照片花花',
      'session_functions' => [
        'setImg',
      ],
      'access' => ['admin','group_admin','sales'],
      'authorized_group_type' => ['Admin','Booking'],
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
      $this->command_data['pre_command'].$this->command_data['cmd']."{服務員名}";
      
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

    $is_server_exist = Server::where('name',$command)->whereIn('partner_id',$partner_id_array)->first();
    if ( !$is_server_exist ){
      return '查無目標服務員，請再次確認(暫存服務員尚無法上傳圖片)';
    }
    $server_img_pairs = ServerImgPair::where('server_id',$is_server_exist->id)->offset(0)->limit(5)->get();
    $messages = [];
    if ( count($server_img_pairs)==0 ){
      return '該服務員尚未設定照片，請向管理員索取';
    }
    foreach (  $server_img_pairs as  $server_img_pair ){
      $object = (object)[];
      $object->type = 'image';
      $image_url = Image::where("id",$server_img_pair->image_id)->first()->image_url;
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
