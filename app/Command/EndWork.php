<?php
namespace App\Command;

use App\Model\Server;
use App\Model\Booking;

use Illuminate\Support\Facades\Redis;

class EndWork extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new EndWork();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '服務員下班',
			'cmd' => '下班',
			'description' => '服務員更新下班時間，並取消今天後面所有訂單',
      'access' => ['admin','group_admin','server'],
      'authorized_group_type' => ['Admin','Server'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$user = $args->user;
		$group = $args->group;
		$command = $args->command;
    // $command = '下班Test泡泡';
    $group = $args->group;
		if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return $message;
		}
		//過濾指令字
		$command_msg = mb_substr($command, mb_strlen($this->command_data['cmd']));

    $admin_access = false; 
    $super_admin_acess = false;

    $group_partners = $group->partners;
		if( count($group_partners)==0 )
		  return "本群未綁定任何廠商";
    $group_partner_id_array = [];
    foreach ( $group_partners as $group_partner ){
      $group_partner_id_array[] = $group_partner->id;
    }
    $partner_id_array = [];
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

		$message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."{服務員名稱}{廠商編號}";

    if ( $admin_access || $super_admin_acess ){
      //管理員代訂
      $match = [];
      if( preg_match('/^[0-9]+/', $command_msg, $match) ){
        $server_name = substr($command_msg,0,strlen($command_msg)-strlen($match[0])-1);
        $partner_id = $match[0];
        $server = Server::where('name',$server_name)->where('partner_id',$partner_id)->first();
        if (!$super_admin_acess){
          $partner_id_array = [];
          foreach ( $user->group_admins as $group_admin ){
            $partner_id_array[] = $group_admin->partner_id;
          }
          if (!in_array($partner_id,$partner_id_array)){
            return  '您不具有廠商id:'.$partner_id.'之管理員身分';
          }
        }
        if (!$server){
          return '廠商id:'.$partner_id.'找不到指定服務員'.$server_name;
        }
      }
      else{
        $servers = Server::where('name',$command_msg)->get();
        if (count($servers)>1){
          $message = '由於未提供廠商代號，且名稱為:'.$command_msg.'的服務員不只一名，請查明後重新輸入';
          if ($super_admin_acess){
            $message = '查詢到不只一名服務員，請明確指定廠商代號及服務員'."\n";
            foreach( $servers as $server ){
             $message .= $server->name.'-'.$server->partner_id."\n";
            }
          return $message;
          }
        }
        if (count($servers)==0){
          if (!$group->server && !empty($command_msg)){
            return '查無名稱為'.$command_msg.'的服務員';
          }
        }
        //到這表示該名稱服務員只有一個
        if (empty($command_msg)){
          $server = Server::where('id',$group->server->id)->first();
        }
        else{
          $server = Server::where('name',$command_msg)->first();
          if (!$server){
            return '查無名稱為'.$command_msg.'之服務員';
          }
        }
        if ( $group->server->id != $server->id){
          return '該群組服務員為:'.$group->server->name.'，不可替其他服務員喊下班';
        }
        $partner_id = $server->partner_id;
        if (!$super_admin_acess){
          $partner_id_array = [];
          foreach ( $user->group_admins as $group_admin ){
            $partner_id_array[] = $group_admin->partner_id;
          }
          if (!in_array($partner_id,$partner_id_array)){
            return  '您不具有該服務員所屬之廠商id:'.$partner_id.'之管理員身分';
          }
        }

      }
    }
    else{
      //進入這邊表示 服務員本人喊下班
      $server = Server::where('line_user_id',$user->id)->where('line_group_id',$group->id)->first();
      if (!$server){
        return '您不具有服務員身分，請通知群組管理員協助';
      }
    }

    $result = $this->endwork($server);
    if ($result->is_legal ){
      $message = '服務員'.$server->name.'於'.$result->end_time.'下班';
    }
    else{
      $message = $result->message;
    }

		return $message;
  }
  //尚未檢查業務群裡面的人員
	protected function SessionFunction( $args=null ) : string {

  }

  private function endwork($server){
    $result = (object) [];
    $daily_schedule = [];
    $daily_schedule = explode("\n",CommandUtil::searchDailyGroupSchedule2($server->line_group_id));
    // 目前班表是空的,不需要做任何異動
    if ( empty($daily_schedule[0]) ){
      $result->is_legal = true;
      $end_time = date('Y-m-d H:i:s');
      $result->end_time = $end_time;
      $update_result = Server::where('id',$server->id)->update(['end_time'=>$end_time]);

      if (!$update_result ){
        $result->is_legal = false;
        $result->message = '下班時間更新失敗，請通知系統工程師';
        return $result;
      }
      return $result;
    }
    $year = date('Y');
    //跨年單問題,如果在01/01,但時間還在工作換日時間內,則年份還在去年
    if ( date('H')<config('app.system.day_split_hour') ){
      $year = date('Y',strtotime('-1 day'));
    }
    $month = substr($daily_schedule[0],0,2);
    $day = substr($daily_schedule[0],3,2);
    $canceled_array =[];

    // 更新服務員下班時間，若服務員今天沒上班就不可以使用下班指令
    if ( strtotime($year.'-'.$month.'-'.$day.' 07:00:00') > strtotime($server->start_time) ){
      //上班時間早於"今日班表"的七點，表示今天未上班
      $result->is_legal = false;
      $result->message = '今日未上班，無法下班';
      return $result;
    }
    // else{
    //   if ( strtotime($server->start_time) < strtotime($server->end_time) ){
    //     $result->is_legal = false;
    //     $result->message = '今日已經下班，無法再次下班';
    //     return $result;
    //   }
    // }
    $end_time = date('Y-m-d H:i:s');
    $result->end_time = $end_time;

    foreach( $daily_schedule as $index => $schedule ){						
      // 第0項是日期,跳過
      if ( $index < 1 ) continue;

      $hour = substr($schedule,0,2);
      $minutes = substr($schedule,3,2);
      if($hour==24){
        $hour = '00';
      }
      $start_time = $year.'-'.$month.'-'.$day.' '.$hour.':'.$minutes.':'.'00';
      //若為凌晨撈凌晨的單，這裡的month day都是前一天的，所以下面這邊要加一天去修正
      if ( $hour<config('app.system.day_split_hour')){
        $start_time = date('Y-m-d H:i:s',strtotime($start_time)+86400);
      }
      // if ( strtotime($start_time) < strtotime(date('Y-m-d H:i:s')) ){
      //   continue;
      // }
      $booking_in_db = Booking::where('start_time',$start_time)
        ->where('server_id',$server->id)
        ->first();

      if ( $booking_in_db ){
        if ( $booking_in_db->status =='Close'){
          continue;
        }
        // $result = $this->cancelSchedule($start_time,$server);
        // if ( !$result->result ){
        //   return $result;
        // }
        // $canceled_array[] = $result->cancel_time;
        $canceled_array[] = $start_time;
      }
    }

    $result->is_legal = true;
    // $result->message = $canceled_array ? "訂單取消成功:\n" : '無取消的訂單';
    if (empty($canceled_array)){
      $result->message = '無取消的訂單';
    }
    else{
      $result->message = "仍有未完成訂單，無法下班:\n";
    }
    $result->after_cancel=true;
    foreach( $canceled_array as $value){
      $result->message .= $value."\n";
    }
    if (empty($canceled_array)){
      $update_result = Server::where('id',$server->id)->update(['end_time'=>$end_time]);
      $result->is_legal = true;
      if (!$update_result ){
        $result->is_legal = false;
        $result->message = '下班時間更新失敗，請通知系統工程師';
        return $result;
      }
      return $result;
    }
    else{
      $result->is_legal = false;
    }
    
    return $result;					
  }
  
	private function cancelSchedule( $cancel_time,$server ){
		$result = (object) [];
		$result->result = true;

		$target_booking = Booking::where('start_time',$cancel_time)
		->where('server_id',$server->id)->first();
    if ( $target_booking->status == 'Close' ){
      return $result;
    }
		//現階段先真的刪除
		$is_update_booking_sucess = $target_booking
		// ->update(['status' => 'Cancel']);
		->delete();
		if ( !$is_update_booking_sucess	){
			$result->result = false;
			$result->message = '訂單取消失敗，請聯繫系統工程師';
			return $result;
		}
		$is_update_schedule_sucess = $server->schedule_units()
		->where('server_id',$server->id)
		->where('start_time','>=', $cancel_time)
		->where('end_time', '<=', $target_booking->end_time)
		->update(['booking_id' => NULL]);
		if ( !$is_update_schedule_sucess	){
			$result->result = false;
			$result->message = '訂單取消後釋放服務員行程失敗，請聯繫系統工程師';
			return $result;
		}


		$result->cancel_time =$cancel_time;
		return $result;
	}

}
