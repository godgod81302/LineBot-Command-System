<?php
namespace App\Command;

use App\Model\Server;
use App\Model\Booking;
use App\Model\GroupAdmin;
use App\Model\temp_group_admin;
use App\Model\PartnerSalesAuth;
use App\Model\PartnerGroupPair;

class CheckTotalSchedule extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new CheckTotalSchedule();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '查訂單總表',
			'cmd' => '表',
			'description' => '查詢指定的廠商服務員，今明兩工作的訂單，範例為 #表{廠商id}',
			'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Admin'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
	  $message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd'].'{廠商id}';

		$group = $args->group;
		if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return $message;
		}

		$command = $args->command;
    $command = trim($command);
		$user = $args->user;
		$partner_id_array = [];

		foreach( $user->group_admins as $group_admin){
			array_push($partner_id_array,$group_admin->partner_id);
		}

    // $servers_id_array = Server::whereIn('partner_id',$partner_id_array)->where('enable','Y')->pluck('id');

		//過濾指令字
		$command_msg = mb_substr($command, mb_strlen($this->command_data['cmd']));

    if (empty($command_msg)){
      if (count($partner_id_array)>1){return '您身上有複數廠商管理員身分，不得省略廠商id，例如: '.$this->command_data['pre_command'].$this->command_data['cmd'].'{廠商id}';}
      $servers_id_array = Server::whereIn('partner_id',$partner_id_array)->pluck('id');
    }
    else{
      if (!is_numeric($command_msg)){
        return $message;
      }
      
      if (!in_array($command_msg,$partner_id_array)){return '您不具有廠商'.$command_msg.'的管理員身分，無法查詢';}
      $servers_id_array = Server::where('partner_id',$command_msg)->pluck('id');
    }

    $work_time_result = CommandUtil::getWorkDayTime();
    $search_datetime = $work_time_result->start_time;
    $end_search_datetime = date("Y-m-d H:i:s",strtotime($work_time_result->end_time)+86400);
 
    $bookings = Booking::where('status','<>','Cancel')
    ->whereIn('server_id',$servers_id_array)
    ->where('start_time','>=',$search_datetime)
    ->Where('end_time','<=',$end_search_datetime)
    ->orderBy('start_time','desc')
    ->get();

    if ( count($bookings)>0 ){
      $date_array =[];
      $date_time = '';
      $msg = '';
      foreach ( $bookings as $booking ){
        // if ( empty($date_time) ){
        //   $date_time = date('m/d',strtotime($booking->start_time))."\n";
        //   $msg .= $date_time;
        // }
        // else{
        //   if ( date('H',strtotime($booking->start_time)) < config('app.system.day_split_hour') ){
        //     $temp_time = strtotime(date('m/d',strtotime($booking->start_time)))-86400;
        //   }
        //   else{
        //     $temp_time = strtotime(date('m/d',strtotime($booking->start_time)));
        //   }
        //   if ( strtotime($date_time) != $temp_time ){

        //     $date_time = date('m/d',strtotime($booking->start_time))."\n";
        //     $msg .= "\n".$date_time;
        //   }
        // }
        if ( date('H',strtotime($booking->start_time)) < config('app.system.day_split_hour') ){
          $temp_time = strtotime(date('m/d',strtotime($booking->start_time)))-86400;
        }
        else{
          $temp_time = strtotime(date('m/d',strtotime($booking->start_time)));
        }
        if ( strtotime($date_time) != $temp_time ){
          $date_time = date('m/d',$temp_time)."\n";
          $msg .= "\n".$date_time;
        }

        //休息的部分，優先判斷處理
        $server_name = Server::where('id',$booking->server_id)->first()->name;
        $msg .= $server_name.')';
        if ( $booking->status == 'Rest' ){
          $msg .= date('H:i',strtotime($booking->start_time)).'~'.date('H:i',strtotime($booking->end_time)).'休息'."\n";
          continue;
        }

        $group_admin = GroupAdmin::where('nickname',$booking->admin_nickname)->first();
        if (!$group_admin){
          $temp_group_admin = temp_group_admin::where('nickname',$booking->admin_nickname)->first();
          if (!$temp_group_admin){
            $msg .= '錯誤，有不存在的管理員暱稱!';
            continue;
          }
          $PartnerSalesAuth = PartnerSalesAuth::where('partner_id',$temp_group_admin->partner_id)->where('sales_id',$booking->sales_id)->first();
          if (!$PartnerSalesAuth){
            $msg .= '錯誤1，此單有異常，請聯繫工程師';
            continue;
          }

        }
        if (!isset($temp_group_admin)){
          $partner_ids = PartnerGroupPair::where('line_group_id',$booking->booking_group_id)->pluck('partner_id');
          $PartnerSalesAuth = PartnerSalesAuth::whereIn('partner_id',$partner_ids)->where('sales_id',$booking->sales_id)->first();
          if (!$PartnerSalesAuth){
            $msg .= '錯誤2，此單有異常，請聯繫工程師';
            continue;
          }
        }


        $special_price =0;
        if ( !empty($booking->note) ){
          $tmp = [];
          $tmp = explode('+',$booking->note);
          foreach ($tmp as $temp){
            preg_match('/^[0-9]+/u',$temp,$tmp);
            if ( isset($tmp[0]) && is_numeric($tmp[0]) ){
              $special_price += $tmp[0];
            }
          }
        }
        $price = $booking->server_fee
        -$special_price
        +$booking->broker_fee
        -$booking->company_cost
        +$booking->company_profit
        -$booking->marketing_cost
        +$booking->sales_profit;

        $start_time = date("H:i",strtotime($booking->start_time));
        if ( date("H",strtotime($booking->start_time))=='00'){
          $start_time=date("24:i",strtotime($booking->start_time));
        }
        $msg .= $start_time.$booking->admin_nickname.$booking->s_time.'/'.$booking->period.'/'.$price;
        if ( !empty($booking->note) ){
          $msg .= '+'.$booking->note.'='.$booking->total_price;
        }
        if (!isset($temp_group_admin)){
          $msg .= '('.$PartnerSalesAuth->sales_group_code;
        }
        else if(!empty($booking->remark)){
          $msg .= '('.$booking->remark;
        }
        unset($temp_group_admin);
        if ( $booking->is_pre_booking =='Y' ){
          $msg .= 'e';
        }
        if ( $booking->status =='Close' )
          $msg .= '*';
        if ( $booking->status =='Ready' )
          $msg .= '^';

        $msg .= "\n";
      }
      return trim($msg,"\n");
    }
    else{
      return '目前無相關班表資訊';
    }

    return $msg;
    


	}
	protected function SessionFunction( $args=null ) : string {
		
  }

}
