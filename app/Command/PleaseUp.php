<?php
namespace App\Command;

use DB;
use App\Model\Booking;
use App\Model\LineUser;
use App\Model\Server;
use App\Model\Sales;
use App\Line\ApiHelper;
use App\Model\RoomServerPair;
use App\Model\RoomImgPair;
use App\Model\Image;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Model\GroupAdmin;
class PleaseUp extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new PleaseUp();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '請上',
			'cmd' => '請上',
			'description' => '請上，#大大請上，管理員可於業務群用來要服務員房號圖',
      'access' => ['admin','group_admin'],
        'session_functions' => [
    'checkServerOk'
        ],
      'reply_questions' => ['請輸入服務員名稱','請上傳服務員照片','請輸入服務員綁定之廠商id(目前建議都先綁2)','請幫服務員設定方案'],
      'authorized_group_type' => ['Booking'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ){
		$group = $args->group;
    $user = $args->user;
		if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return $message;
      }
    $partner = $group->partners->first();
    if( !$partner )
      return "本群未綁定任何廠商";
    // if( $group->partners->count()>1 )
    //   return "本群綁定超過2個廠商";
    //去頭之後的內容
    $command = $args->command;
    $command = substr($command,strlen( $this->command_data['cmd']));
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
      return '您不具管理員身分，無法使用此指令';
    }      

    $servers = Server::where('name',$command)->whereIn('partner_id',$partner_id_array)->get();
    if (count($servers)>1){
      return '抱歉!有多位名稱為'.$command.'之服務員，故無法直接提供房號圖，請聯繫總機要圖';
    }
    $server = $servers->first();

    $room_server_pair = RoomServerPair::where('server_id',$server->id)->first();
    if ( !$room_server_pair ){
      return '該服務員暫無房間照片，請先向管理員索取';
    }

    $room_img_pairs = RoomImgPair::where('room_data_id',$room_server_pair->room_data_id)->where('img_for','room')->offset(0)->limit(5)->get();
    $messages = [];
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
    if (count($messages)==0){
      $messages = '目前無房間照片';
    }
    return $messages; 

	}
	protected function SessionFunction( $args=null ){

  }
	
}
