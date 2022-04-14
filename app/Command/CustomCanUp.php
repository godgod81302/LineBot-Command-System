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
class CustomCanUP extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new CustomCanUP();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '可上',
			'cmd' => '可上',
			'description' => '可上',
            'access' => ['admin','server'],
            'session_functions' => [
				'checkServerOk'
            ],
            'reply_questions' => ['請輸入服務員名稱','請上傳服務員照片','請輸入服務員綁定之廠商id(目前建議都先綁2)','請幫服務員設定方案'],
            'authorized_group_type' => ['Server'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ){
		$group = $args->group;
		if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return $message;
        }
        $servers_in_the_group = Server::where('line_group_id',$group->id)->get();
        if ( count($servers_in_the_group) != 1 ){
            if ( count($servers_in_the_group) == 0 ){
                return '無任何服務員屬於當前群組';
            }
            return '有多位服務員屬於當前群組，群組發生錯誤，一服務群只能有一位服務員，請重新確認群組成員，或者聯繫工程師查詢';
        }
        $server  =$servers_in_the_group->first();

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
          $messages = '目前無房間照片，請上傳圖片'."\n".'或輸入 #{數字} 執行功能以繼續'."\n".'#1設檢照#2設房照#3查檢照#4查房照#5清檢照#6清房照#7完成';
        }
        return $messages;


        exit;
        //以下程式碼先封印
        $helper = ApiHelper::helper(env('LINE_BOT_CHANNEL_ACCESS_TOKEN'));

		$command = $args->command;
        $user = $args->user;

		if ( !$user->server ){
            //管理員代可上
            $admin_access = false;
            foreach( $user->group_admins as $admin ){
                if( $admin->partner->id==$server->partner_id){
                    $admin_access = true;
                    break;
                }
            }
            if ( !$admin_access ){
                $error_msg = '您沒有任何管理員代訂權限，非法操作已記錄';
                $log = Log::channel('ileagal-call');
                $log->debug(['user_id'=>$user->id,'msg'=>$error_msg,'command'=>$command]);
                return $error_msg;
            }
        }

        $target_booking = Booking::whereBetween('start_time',[date('Y-m-d H:i',strtotime('-2 hour')).':00',date('Y-m-d H:i',strtotime('+2 hour')).':00'])
        ->where('status','Arrived')
        ->where('server_id',$server->id)
        ->orderBy('start_time','asc')->get();
        if ( count($target_booking)>1 ){
            return '該小姐當前有兩筆以上訂單顯示客到，可能有錯誤發生，請聯繫工程師';
        }
        else if( count($target_booking)<1  ){
            return '目前前後兩小時內無客到訂單，請聯繫管理員再次確認';
        }
        $target_booking = $target_booking->first();
        $result = $target_booking->update([ 'status' => 'Aboard' ]);
        if ( $result ){
            //這裡要改成吐給客到的群組(撈該服務員的session)
            $sale =   Sales::where('id',$target_booking->sales_id)->first();
            $time = date('Hi',strtotime($target_booking->start_time));
            if ( date('H',strtotime($target_booking->start_time)) == '00' ){
                $time = '24'.date('i',strtotime($target_booking->start_time));
            }
            $server = Server::where('id',$target_booking->server_id)->first();
 
            $msg = $time.'服務員'.$server->name.'可上，可以開始導客請跟總機要導客文案'; 
            $helper->push($sale->line_user_id, [['type' => 'text','text'=>$msg]], true);
            $msg = $time.'服務員'.$server->name.'可上，以下導客圖請管理員轉給業務';
            $messages = [
                $msg,
            ];

            $temps = [];
            $temps = CommandUtil::getRoomPhoto($server);
            foreach( $temps as $temp ){
              array_push( $messages , $temp);
            }
            return $messages;
        }
        else{
            return '訂單狀態更新失敗，請聯繫系統工程師';
        }

	}
	protected function SessionFunction( $args=null ){
		$group = $args->group;
		if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return $message;
		}
		$command = $args->command;
		$user = $args->user;
		$session  = Redis::hgetall(md5($user->id.$group->id));
		$msg_list = json_decode($session['msg_list'],true);
		$index = count($msg_list);

		$function_name = $this->command_data['session_functions'][$index];

		$result =  $this->$function_name($command);
        $helper = ApiHelper::helper(env('LINE_BOT_CHANNEL_ACCESS_TOKEN'));
        $target_booking = Booking::where('id',$session['booking_id'])->first();
       
        if( $result ){

            if ( $target_booking->status != 'Arrived' ){
                Redis::del(md5($user->id.$group->id));
                return '訂單狀態並非客到，請聯繫系統工程師';
            }
              
            $booking = Booking::where('id',$session['booking_id'])
            ->update(['status' => 'Aboard']);
            
            if ( $booking ){
                Redis::del(md5($user->id.$group->id));
                // $line_user = LineUser::where('id',$session['update_user_id'])->first();
                // $msg = 'To '.$line_user->latest_name."\n".'服務員可上，可以開始導客請跟總機要導客文案';
                $server = Server::where('id',$target_booking->server_id)->first();
                $msg = '服務員'.$server->name.'可上，可以開始導客請跟總機要導客文案';
                $messages = [
                    [	'type' => 'text',	'text' =>  $msg ],
                ];
                $result = $helper->push($session['csup_group_id'], $messages, true);
                return '訂單狀態已更新為可上，並已通知客到發起者';
            }
        }
        else{
            if ( mb_substr($command,0,1) == '等' ){
                // $command = mb_substr($command ,)
            }
            
        }
        return date('H:i',strtotime($target_booking->start_time)).'客到可上?'."\n".'CS UP OK?';
    }
    
    private function checkServerOk($command){
        $ok_array = ['Ok','OK','oK','oK','可上','可以','YES','yes','Yes','沒問題'];
        $is_server_said_ok = false;
        foreach( $ok_array as $value ){
            if ( $command === $value  ){
                $is_server_said_ok = true;
            }
        }
        
        return $is_server_said_ok;
    }
	
}
