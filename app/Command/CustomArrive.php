<?php
namespace App\Command;

use App\Model\LineUser;
use App\Model\Server;
use App\Model\Booking;
use App\Model\Partner;
use App\Model\PartnerSalesAuth;
use App\Model\RoomServerPair;
use App\Model\RoomImgPair;
use App\Model\Image;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use App\Line\ApiHelper;

class CustomArrive extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new CustomArrive();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
      'pre_command' => '#',
      'session_functions' => [
				'updateBookingStatus',
			],
			'name' => '客人到',
			'cmd' => '客到',
			'description' => '客人到場',
			'args' => [],
      'access' => ['admin','group_admin','sales'],
      'reply_questions' => ['客到狀態更新完成'],
      'authorized_group_type' => ['Booking'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ){
    
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
    if ( isset($user->sales) ){
      $sales = $user->sales;
    }
    $group_partners = $group->partners;
    $admin_access = false; 
    $super_admin_acess = false;
    $partner_id_array=[];
    $group_partner_id_array = [];
    foreach ( $group_partners as $group_partner ){
      $group_partner_id_array[] = $group_partner->id;
    }
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

		//過濾指令字
    $command_msg = substr($command, strlen($this->command_data['cmd']));
    $daiding_check = [];
    $daiding_check  = explode(')',$command_msg);
    if ( !empty($daiding_check[1]) ){
      if ( preg_match('/^[0-9]{11}/',$daiding_check[0]) ){
        $sales = Sales::where('sn','S'.$daiding_check[0])->first();
        if( !$sales ){
          return '搜尋不到指定業務';
        }
        $command_msg = $daiding_check[1];
      }
      else { return "業務id格式不正確";}
    }
    $msg_list = [];
    //這裡表示只有輸入客到
		if ( empty($command_msg) ){ 
      if ( !isset($sales) ){
        return '由於您不具有業務身分，因此無法推估服務員訂單，管理員請用訂單編號代替人名';
      }
      $sales_partners  = PartnerSalesAuth::where('sales_id',$sales->id)->get();
      if ( count($sales_partners) == 0 ){
        return '您未與任何廠商綁定，不可使用客到功能';
      }

      foreach( $sales_partners as $sales_partner){
        array_push($partner_id_array,$sales_partner->id);
      }

      $servers = Server::whereIn('partner_id',$partner_id_array)->get();
      $servers_id_array = [];

      foreach( $servers as $server){
        array_push($servers_id_array,$server->id);
      }

      if ( $servers->count()==0 ){
        return '您的合作廠商目前尚未設定服務員';
      }

      exit;
      //以下程式暫時先封印
      $target_bookings = Booking::whereBetween('start_time',[date('Y-m-d H:i',strtotime('-2 hour')).':00',date('Y-m-d H:i',strtotime('+2 hour')).':00'])
      ->where('status','Pending')
      ->whereIn('server_id',$servers_id_array)
      ->orderBy('start_time','asc')->get();
      
      if ( $target_bookings->count() == 0 ){
        return '您當前無任何相關之訂單，請再次確認';
      }

      if ( $target_bookings->count() == 1 ){
        foreach ( $target_bookings as $target_booking ){
          $result = $this->updateBookingStatus($group,$target_booking,'Arrived',$user->id);
          if ( !$result->sucessed ){
            return $result->message;
          }
          else{
            return $result->messages;
          }
        }

      }
			
    }
      if( preg_match('/^[a-zA-Z]*\p{Han}*[\x{3105}-\x{3129}]*[a-zA-Z]*/u',$command_msg,$tmp) ){
      $server_name = $tmp[0];
      if ( !isset($sales) ){
        return '由於您不具有業務身分，因此無法推估服務員訂單，管理員請用訂單編號代替人名';
      }
      $sales_partners  = PartnerSalesAuth::where('sales_id',$sales->id)->get();
      if ( count($sales_partners) == 0 ){
        return '您未與任何廠商綁定，不可使用客到功能';
      }

      foreach( $sales_partners as $sales_partner){
        array_push($partner_id_array,$sales_partner->id);
      }

      $servers = Server::where('name','like',"%".$server_name."%")->whereIn('partner_id',$partner_id_array)->get();
      $servers_id_array = [];

      foreach( $servers as $server){
        array_push($servers_id_array,$server->id);
      }

      if ( $servers->count()==0 ){
        return "查無欲查詢之相關服務員，請再次確認服務員名稱，或 改用訂單編號 \n".'範例: #客到{訂單編號}';
      }
      if ( $servers->count()>1 ){
        return '查到多比相似名稱的服務員資料，請聯繫管理員處理';
      }
      $room_server_pair = RoomServerPair::whereIn('server_id',$servers_id_array)->first();
      if ( !$room_server_pair ){
        return '該服務員暫無照片，請先向管理員索取';
      }
      $room_img_pairs = RoomImgPair::where('room_data_id',$room_server_pair->room_data_id)->where('img_for','checkpoint')->offset(0)->limit(5)->get();
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
        $messages = '目前無檢查點照片';
      }
      return $messages;
      exit;
      $target_bookings = Booking::whereBetween('start_time',[date('Y-m-d H:i',strtotime('-2 hour')).':00',date('Y-m-d H:i',strtotime('+2 hour')).':00'])
      ->where('status','Pending')
      ->whereIn('server_id',$servers_id_array)
      ->orderBy('start_time','asc')->get();
      
      if ( $target_bookings->count() == 0 ){
        return '您當前無相關服務員:'.$server_name."之訂單，請再次確認服務員名稱，或 改用訂單編號 \n".'範例: #客到{訂單編號}';
      }

      if ( $target_bookings->count() == 1 ){
        foreach ( $target_bookings as $target_booking ){
          $result = $this->updateBookingStatus($group,$target_booking,'Arrived',$user->id);
          if ( !$result->sucessed ){
            return $result->message;
          }
          else{
            return $result->messages;
          }
        }

      }

    }
    else if($command_msg=='?'){
      return '客人抵達時，由業務發起客到通知，格式如下'."\n".'
      #客到小花 (默認為此發話人的尚未發生的小花的最近的訂單)'."\n".'
      #客到88888 (只對自己的訂單有效)'."\n".'
      #客到 (默認為此發話人的尚未發生的最近的訂單，若此發話人有多個訂單，則會產生哪個定單客到的確認程序)';
    }
    else{
      return '輸入不符合格式，客人抵達時，由業務發起客到通知，格式如下'."\n".'
      #客到小花 (默認為此發話人的尚未發生的小花的最近的訂單)'."\n".'
      #客到88888 (只對自己的訂單有效)'."\n".'
      #客到 (默認為此發話人的尚未發生的最近的訂單，若此發話人有多個訂單，則會產生哪個定單客到的確認程序)';
    }
    $booking_id_array = [];
    $message = "搜尋到多筆相關訂單，請指定第幾張訂單：\n";
    $index = 1;

    foreach( $target_bookings as  $target_booking){
      $message .= $index.'.'.$sales->sn.'預約'.date('Hi',strtotime($target_booking->start_time)).$target_booking->server->name."\n";
      array_push($booking_id_array,$target_booking->id);
      $index++;
    }
 
		Redis::del(md5($user->id.$group->id));
		$session_exist  = Redis::hmget(md5($user->id.$group->id),'timestamp');

		if ( !$session_exist[0] ){
			 Redis::hmset(md5($user->id.$group->id),'timestamp',strtotime('now'),'classname',__CLASS__,'msg_list',json_encode($msg_list),'booling_id_list',json_encode($booking_id_array));
    }
    
		$message = trim($message,"\n");

		return $message;
	}
  
  private function updateBookingStatus($group,$target_booking,$status,$update_user_id){
    $result = (object) ['message'=>''];
    $result->sucessed = false;

    if (  $target_booking->status == 'Pending' ){
      $update_result = $target_booking->update(['status' => $status,'custom_arrived_group_id' => $group->id,]);
      if ($update_result){
        $result->sucessed = true;
      }
      else{
        $result->message =  '更新失敗，請聯繫系統工程師';
        return $result;
      }
    }
    else{
      $result->message =  '訂單目前狀態:'.$target_booking->status.'，因此無須再輸入客到';
      return $result;
    }

    $server = Server::where('id',$target_booking->server_id)->first();
    $helper = ApiHelper::helper(env('LINE_BOT_CHANNEL_ACCESS_TOKEN'));
    $user = LineUser::where('id',$server->line_user_id)->first();
    if ( !$user ){
      $msg = date('H:i',strtotime($target_booking->start_time)).'客到可上?'."\n".'該服務員無綁定用戶id，請管理員代喊#可上';
      $helper->push($server->line_group_id, [[	'type' => 'text',	'text' =>  $msg ],], true);
    }
    else{
      Redis::del(md5($user->id.$server->line_group_id));
      Redis::hmset(md5($user->id.$server->line_group_id),'timestamp',strtotime('now'),'classname','App\Command\CustomCanUp','msg_list',json_encode([]),'booking_id',$target_booking->id,'csup_group_id',$group->id,'update_user_id',$update_user_id,'server_name',$user->latest_name);
      $msg = date('H:i',strtotime($target_booking->start_time)).'客到可上?'."\n".'CS UP OK?';
      $helper->push($server->line_group_id, [[	'type' => 'text',	'text' =>  $msg ],], true);
    }

    $messages = [
      '服務員:'.$server->name.'服務點導引圖如下，請等待總機回覆是否可上',
    ];
    $temps = [];
    $temps = CommandUtil::getServicePointPhoto($server);
    foreach( $temps as $temp ){
      array_push( $messages , $temp);
    }
    $result->messages = [];
    $result->messages = $messages;


    return $result;
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
    $booling_id_list = json_decode($session['booling_id_list'],true);
		$index = count($msg_list);
 
    $function_name = $this->command_data['session_functions'][$index];
    if ( is_numeric($command) ){
      if ( isset($booling_id_list[$command-1]) ){
        $target_booking = Booking::where('id',$booling_id_list[$command-1])->first();
        $result =  $this->$function_name($target_booking,'Arrived');
      }
      else{
        return '未包含此數字選項';
      }
    }
    else{

      return '請只輸入數字，是第幾筆客到';
    }

		if (  $result->sucessed ){
      Redis::del(md5($user->id.$group->id));
      return $result->message;
		}
		else{
			return '錯誤:'."\n". $result->message;
		}
		
		
	}

	
}
