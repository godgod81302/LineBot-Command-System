<?php
namespace App\Command;

use DB;
use App\Model\Booking;
use App\Model\LineUser;
use App\Model\Server;
use App\Line\ApiHelper;
use App\Model\temp_group_admin;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Model\GroupAdmin;
class CustomInEn extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new CustomInEn();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '客進',
			'cmd' => 'in',
			'description' => '客人進，已完成收款',
            'access' => ['admin','group_admin','server','temp_group_admin'],
            'session_functions' => [
				'checkServerOk'
            ],
            'reply_questions' => ['請輸入服務員名稱','請上傳服務員照片','請輸入服務員綁定之廠商id(目前建議都先綁2)','請幫服務員設定方案'],
            'authorized_group_type' => ['Server'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
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
                $is_temp_admin = temp_group_admin::where('line_user_id',$user->id)->where('partner_id',$server->partner_id)->first();
                if ( !$is_temp_admin){
                    $error_msg = '您沒有任何管理員代訂權限，非法操作已記錄';
                    $log = Log::channel('ileagal-call');
                    $log->debug(['user_id'=>$user->id,'msg'=>$error_msg,'command'=>$command]);
                    return $error_msg;
                }
                else{
                    $admin_access = true;
                }
            }
        }
        
        // if ( !$admin_access ){
        //     $check_custom_outs = Booking::whereBetween('end_time',[date('Y-m-d H:i',strtotime('-1 hour')).':00',date('Y-m-d H:i',strtotime('now')).':00'])
        //     ->whereIn('status',['Pending','Arrived','Aboard','Ready'])
        //     ->where('server_id',$server->id)
        //     ->orderBy('start_time','asc')->get();
        //     if ( count($check_custom_outs) > 0  ){
        //         return '前客未出，客進無效';
        //     }
        // }
        // else{
            // $check_custom_outs = Booking::where('end_time','<',date('Y-m-d H:i:00',strtotime('now')))
            // ->where('status','Ready')
            // ->where('server_id',$server->id)
            // ->orderBy('start_time','desc')->first();

            // if ( $check_custom_outs > 0  ){
            //     $message_first = '前客未出，已使用管理權將客進改出，受影響單如下'."\n";
            //     foreach ( $check_custom_outs as $check_custom_out ){
            //         $result = $check_custom_out->update([ 'status' => 'Close' ]);
            //         if ($result){
            //             $message_first .= $check_custom_out->start_time."\n";
            //         }
            //     }
            //     $message_first .= '-----------------';
            // }
        // }
        
        // 客進 抓最近的客出單，然後用客出單的開始時間以後來看，哪一筆離當前時間最近
        // 如果喊客進要回饋客進厚的班表
        $work_time_result = CommandUtil::getWorkDayTime();
        $last_csout_booking = Booking::
        where('start_time','>',$work_time_result->start_time)
        ->where('server_id',$server->id)
        ->where('status','Close')
        ->orderBy('start_time','desc')->first();
        
        $start_time = $work_time_result->start_time;
        if ( $last_csout_booking ){
            $start_time = $last_csout_booking->start_time;
        }

        $target_booking = Booking::where('start_time','>',$start_time)
        ->where('server_id',$server->id)
        ->whereIn('status',['Pending','Arrived','Aboard'])
        ->orderBy('start_time')->first();


        // ->orderByRaw('ABS(NOW() - start_time) asc')->first();
        if (!$target_booking ){
            $msg = '服務員當前無已可進訂單，無法使用客進指令';
            if ( isset($message_first) ){
                return $message_first."\n".$msg;
            }
            return $msg;
        }
        $last_booking = Booking::where('start_time','<',$target_booking->start_time)
        ->where('server_id',$server->id)
        ->where('start_time','>',$work_time_result->start_time)
        ->where('status','<>','Cancel')
        ->orderBy('start_time','desc')
        ->first();

        if ($last_booking){
            if ( $last_booking->status=='Ready' ){
                return '抱歉，'.date('Hi',strtotime($last_booking->start_time)).'前單未出，'.date('Hi',strtotime($target_booking->start_time)).'不可客進';
            }
        }

        $result = $target_booking->update([ 'real_start_time' => date('Y-m-d H:i:s'),'status' => 'Ready' ]);
        if ( $result ){

            $daily_bookings = CommandUtil::searchDailyGroupSchedule($group->id);
            if ( empty($daily_bookings) ){
                return '目前無相關班表資訊';
            }
            $msg = $daily_bookings;

            if ( isset($message_first) ){
                return $message_first."\n".$msg;
            }
            return $msg;
        }
        else{
            $msg = '訂單狀態更新失敗，請聯繫系統工程師';
            if ( isset($message_first) ){
                return $message_first."\n".$msg;
            }
            return $msg;
        }

	}
	protected function SessionFunction( $args=null ) : string {
        // exit;
		// $group = $args->group;
		// if( !$group || $group->enble=='N' ){
		// 	$message = "未授權";
		// 	return $message;
		// }
		// $command = $args->command;
		// $user = $args->user;
		// $session  = Redis::hgetall(md5($user->id.$group->id));
		// $msg_list = json_decode($session['msg_list'],true);
		// $index = count($msg_list);

		// $function_name = $this->command_data['session_functions'][$index];

		// $result =  $this->$function_name($command);
        // $helper = ApiHelper::helper(env('LINE_BOT_CHANNEL_ACCESS_TOKEN'));
        // $target_booking = Booking::where('id',$session['booking_id'])->first();
       
        // if( $result ){

        //     if ( $target_booking->status != 'Aboard' ){
        //         Redis::del(md5($user->id.$group->id));
        //         return '訂單狀態並非客到，請聯繫系統工程師';
        //     }
              
        //     $booking = Booking::where('id',$session['booking_id'])
        //     ->update(['status' => 'Ready']);
            
        //     if ( $booking ){
        //         Redis::del(md5($user->id.$group->id));
        //         // $line_user = LineUser::where('id',$session['update_user_id'])->first();
        //         // $msg = 'To '.$line_user->latest_name."\n".'服務員可上，可以開始導客請跟總機要導客文案';
        //         $server = Server::where('id',$target_booking->server_id)->first();
        //         $msg = '服務員'.$server->name.'可上，可以開始導客請跟總機要導客文案';
        //         $messages = [
        //             [	'type' => 'text',	'text' =>  $msg ],
        //         ];
        //         $result = $helper->push($session['csup_group_id'], $messages, true);
        //         return '訂單狀態已更新為可上，並已通知客到發起者';
        //     }
        // }
        // else{
        //     if ( mb_substr($command,0,1) == '等' ){
        //         // $command = mb_substr($command ,)
        //     }
            
        // }
        // return date('H:i',strtotime($target_booking->start_time)).'客到可上?'."\n".'CS UP OK?';
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
