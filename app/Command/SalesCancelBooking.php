<?php
namespace App\Command;

use App\Model\Booking;
use App\Model\ScheduleUnit;
use App\Model\Server;
class SalesCancelBooking extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new SalesCancelBooking();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '取消訂單',
			'cmd' => '取消',
			'description' => '業務取消訂單',
			'access' => ['admin','group_admin','sales'],
			'authorized_group_type' => ['Admin','Booking'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$user = $args->user;
		$group = $args->group;
		$command = $args->command;
    if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return config('app.debug') ? $message : null;
		}
    
		$message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."{訂單編號}";
			
		$command = preg_replace('/\s+/',' ',$command);
 
		
		$partner = $group->partners->first();
		if( !$partner )
		  return "本群未綁定任何廠商，無法取消";
    if( $group->partners->count()>1 )
      return "本群綁定超過2個廠商，不接受取消";
    //去頭之後的內容
    $command = substr($command,strlen( $this->command_data['cmd']));
		if ( !is_numeric($command) ){
      return '請輸入訂單編號';
    }

    $admin_access = false;
		foreach( $user->group_admins as $admin ){
			if( $admin->partner->id==$partner->id ){
				$admin_access = true;
				break;
			}
      else if ( $admin->partner->id == 1 ){
        $admin_access = true;
				break;
      }
		}

    if ( !$admin_access ){
      $sales = $user->sales;
      if ( !$sales ){
        return '您不具業務身分，無法取消';
      }
    }

    if ( isset($sales) ){
      $result  = $this->cancelSchedule($command,$sales);
    }
    else{
      $result  = $this->cancelSchedule($command);
    }

    $message = '訂單取消成功';
    if (!$result->result){
      $message = $result->message;
    }
		return $message;
	}
	protected function SessionFunction( $args=null ) : string {
		
	}
  private function cancelSchedule( $booking_id,$sales=false ){
		$result = (object) [];
		$result->result = true;

		$target_booking = Booking::where('id',$booking_id)
    ->first();
    if (!$target_booking){
      $result->result = false;
      $result->message = '訂單取消失敗，該訂單已不存在';
      return $result;
    }
    if ( $sales ){
      if ( $target_booking->sales_id != $sales->id ){
        $result->result = false;
        $result->message = '訂單取消失敗，您並非本單的下定業務，請聯繫管理員';
        return $result;
      }
    }
    if ( $target_booking->status == 'Close' )
      $result->result = false;
      $result->message = '訂單取消失敗，該訂單已完成不得取消';
      return $result;
		//現階段先真的刪除
		$is_update_booking_sucess = $target_booking
		// ->update(['status' => 'Cancel']);
		->delete();
		if ( !$is_update_booking_sucess	){
			$result->result = false;
			$result->message = '訂單取消失敗，請聯繫系統工程師';
			return $result;
		}
    $server = Server::where('id',$target_booking->server_id)->first();
		$is_update_schedule_sucess = $server->schedule_units()
		->where('server_id',$server->id)
		->where('start_time','>=', $target_booking->start_time)
		->where('end_time', '<=', $target_booking->end_time)
		->update(['booking_id' => NULL]);
		if ( !$is_update_schedule_sucess	){
			$result->result = false;
			$result->message = '訂單取消後釋放服務員行程失敗，請聯繫系統工程師';
			return $result;
		}

		return $result;
	}

}
