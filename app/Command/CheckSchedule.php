<?php
namespace App\Command;

use App\Model\Partner;
use App\Model\PartnerSalesAuth;
use App\Model\Sales;
use App\Model\LineUser;

class CheckSchedule extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new CheckSchedule();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '查班表',
			'cmd' => '查班表',
			'description' => '#查班表{月日，例如:0525}',
			'access' => ['admin','group_admin','sales'],
      'authorized_group_type' => ['Server'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
	  $message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd'].'{月日，例如:0525}';
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
		$partner_id_array = [];
		// foreach( $user->group_admins as $group_admin){
		// 	array_push($partner_id_array,$group_admin->partner_id);
		// }
		//過濾指令字
    $command_msg = substr($command, strlen($this->command_data['cmd']));
		if (!empty($command_msg)){
			$command = mb_substr($command,mb_strlen($this->command_data['cmd']));
			$month = substr($command,0,2);
			$day = substr($command,2,2);
			$year = date('Y');
			if ( date('H')<config('app.system.day_split_hour') ){
				$year = date('Y',strtotime('-1 day'));
			}
			$date = $year.'-'.$month.'-'.$day;
			$work_time_result = CommandUtil::getWorkDayTime();
			if ( strtotime($date) > strtotime($work_time_result->start_time) ){
				$split_hour = config('app.system.day_split_hour');
				$split_hour_string = str_pad($split_hour,2,'0',STR_PAD_LEFT);
				$search_datetime = date("Y-m-d {$split_hour_string}:00:00",strtotime($date));
		
				$end_search_datetime = date("Y-m-d {$split_hour_string}:00:00", strtotime($date)+86400);
			}
		}


		if ( isset($search_datetime) ){
			$result = CommandUtil::searchDailyGroupSchedule($group->server->line_group_id,$search_datetime,$end_search_datetime);
		}
		else{
			$result = CommandUtil::searchDailyGroupSchedule($group->id);
		}

    if ( empty($result) ){
      return '目前無相關班表資訊';
    }
    return $result;
    


	}
	protected function SessionFunction( $args=null ) : string {
		
  }

}
