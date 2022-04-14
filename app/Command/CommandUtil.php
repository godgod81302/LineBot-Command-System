<?php
namespace App\Command;
use DB;
use App\Model\Booking;
use App\Model\Server;
use App\Model\Service;
use App\Model\ScheduleUnit;
use App\Model\PartnerSalesAuth;
use App\Model\PartnerGroupPair;
use App\Model\GroupAdmin;
use App\Model\RoomServerPair;
use App\Model\ServerImgPair;
use App\Model\RoomImgPair;
use App\Model\Area;
use App\Model\RoomData;
use App\Model\ServicePoint;
use App\Model\ServicePointImgPair;
use App\Model\Image;
use App\Model\temp_group_admin;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class CommandUtil
{
  public static function getServerNextFreeTime($server, $datetime=null){
		$is_server_not_work_at_datetime = false;
		if ( strtotime($datetime)<strtotime($server->start_time)){
			//今天有班，但查詢的時間還沒到服務員上班時間
			$is_server_not_work_at_datetime = true;
			$datetime = self::timeFormate($server->start_time);
		}

		// $work_start_timestamp = strtotime($server->start_time);
		$work_end_timestamp = strtotime($server->end_time);
		
		// $work_start_time = date('Y-m-d H:i:s', $work_start_timestamp);
		$work_end_time = date('Y-m-d H:i:s', $work_end_timestamp);
		
		$datetime = $datetime ?? date('Y-m-d H:i:s');
		
		$message = '';
		if( strtotime($datetime)>$work_end_timestamp ){
			$message = "查詢時間已超過".$server->name."服務時間(".date('m-d H:i',$work_end_timestamp).")";
			return (object)[
				'abletime' => null,
				'datetime' => null,
				'periods' => [],
				'message' => $message,
			];
		}

		$available = (object)[
			'abletime' => null,
			'datetime' => $datetime,
			'is_all_period_able' => false,
			'periods' => '',
			'message' => '',
		];

		$min_service_period = $server->services->where('description','<>','特殊服務')->where('status','<>','hide')->sortBy('period')->first()->period;

		$datetime_timestamp = strtotime($datetime);
		$is_find_closed_ok_time = false;

		//自想查詢的目標時間開始，加上最短服務時間後看是不是都有空
			$end_timestamp = $datetime_timestamp+$min_service_period*60;
	
			$target_schedule = ScheduleUnit::whereBetween('start_time',[$datetime,date('Y-m-d H:i:s',$end_timestamp)])
			->orWhere(function($query) use ($datetime,$end_timestamp)
			{
					$query->whereBetween('end_time',[$datetime,date('Y-m-d H:i:s',$end_timestamp)]);
			})
			->where('server_id',$server->id)
			->whereNotNull('booking_id')
			->orderBy('start_time')
			->first();

			//進去表示當前時間連最短方案都沒空
			if ($target_schedule){
				//接著要試著找到至少最短方案可以的時間
				$bool_find_ok_time = false;
				$not_ok_schedule_time = $target_schedule->start_time;
				while(!$bool_find_ok_time){
					//這裡要從上面抓到的不行的時間繼續往下抓，找到空白的時間再來檢驗，可是如果到下班前都沒有空白，就break出迴圈
					$schedule = ScheduleUnit::where('start_time','>=',$not_ok_schedule_time)
					->where('start_time','<=',$work_end_time)
					->where('server_id',$server->id)
					->whereNull('booking_id')
					->first();
					
					if (!$schedule){
						break;
					}
					
					//上面時間抓到的第一個空白，先來看看他能不能塞入最短時間服務，如果連塞最短時間服務都會超出下班時間，那就break
					$end_timestamp  = strtotime($schedule->start_time)+$min_service_period*60;
					if ($end_timestamp>$work_end_timestamp){
						break;
					}
					$maybe_ok_schedule = ScheduleUnit::where('start_time','>=',$schedule->start_time)
					->where('end_time','<=',date('Y-m-d H:i:s',$end_timestamp))
					->where('server_id',$server->id)
					->whereNotNull('booking_id')
					->first();

					if ($maybe_ok_schedule){
						$not_ok_schedule_time = $maybe_ok_schedule->start_time;
						continue;
					}
					
					$bool_find_ok_time = true;
					//能到這邊表示已經找到可以塞入最小方案的空白時段，所以要驗證該空白能否塞下更長的方案，如果可以塞下全方案，就皆大歡喜

					$is_server_free = self::getSingalFreeServerOnSpecificTime($server,$schedule->start_time);
					$max_service_period = $server->services->where('description','<>','特殊服務')->where('status','<>','hide')->sortByDesc('period')->first()->period;
					//這是在看該時段能否塞下最長服務方案，如果可以，那就搞只需要輸出一條訊息
					$available->abletime = date('Hi',strtotime($schedule->start_time));
					$available->datetime = $schedule->start_time;
					$available->periods = array_unique($is_server_free->periods);
					$temp_datetime = date('Hi',strtotime($schedule->start_time));
					if (substr($temp_datetime,0,2)=='00'){
						$temp_datetime = '24'.substr($temp_datetime,2,2);
					}
					$available->message .= $server->name.$temp_datetime.'可';
					$index = 1;
					if ( max($is_server_free->periods) == $max_service_period ){
						$available->is_all_period_able = true;
						return $available;
					}
					else{
						foreach( $available->periods as $period ){
							$available->message .= $period;
							if ($index!=count($available->periods)){
								$available->message .= '/';
							}
							else{
								$available->message .= "\n";
							}
							$index ++;
						}
						//這指的是找到能塞入最大服務時常的空白時段
						$bool_find_max_ok_time = false;
						$not_ok_schedule_time = date('Y-m-d H:i:00',strtotime($schedule->start_time));
						while(!$bool_find_max_ok_time){
							$schedule1 = ScheduleUnit::where('start_time','>=',$not_ok_schedule_time)
							->where('start_time','<=',$work_end_time)
							->where('server_id',$server->id)
							->whereNull('booking_id')
							->first();
							if (!$schedule1){
								echo '下班前已無可排入全方案之空白時段';
								$available->message .= $server->name.'其他已滿';
								return $available;
							}
							//上面時間抓到的第一個空白，先來看看他能不能塞入最長時間服務，如果連塞最長時間服務都會超出下班時間，那就break
							$end_timestamp1  = strtotime($schedule1->start_time)+$max_service_period*60;
							if ($end_timestamp1>$work_end_timestamp){
								break;
							}
							$maybe_ok_schedule1 = ScheduleUnit::where('start_time','>=',$schedule1->start_time)
							->where('start_time','<=',date('Y-m-d H:i:s',$end_timestamp1))
							->where('server_id',$server->id)
							->whereNotNull('booking_id')
							->first();
							if ($maybe_ok_schedule1){
								$not_ok_schedule_time = $maybe_ok_schedule1->start_time;
								continue;
							}
							$bool_find_max_ok_time = true;
						}
						if (!$bool_find_max_ok_time){
							break;
						}

						$temp_datetime1 = date('Hi',strtotime($schedule1->start_time));
						if (substr($temp_datetime1,0,2)=='00'){
							$temp_datetime1 = '24'.substr($temp_datetime1,2,2);
						}
						$available->message .= $server->name.$temp_datetime1.'可';
						$periods = array_unique($server->services->where('description','<>','特殊服務')->where('status','<>','hide')->sortBy('period')->pluck('period')->all());
						$index = 1;
						if ( !$bool_find_max_ok_time ){
							foreach( $periods as $period ){
								$available->message .= $period;
								if ($index!=count($periods)){
									$available->message .= '/';
								}
								$index ++;
							}
						}
						return $available;
					}
				}
				if (!$bool_find_ok_time){
					echo '該服務員後續已無可預約時間';
					$available->message = $server->name.'已滿';
					return $available;
				}
				else if (!$bool_find_max_ok_time){
					echo '該服務員後續已無可放入全方案時段';
					$available->message .= $server->name.'其他已滿';
					return $available;
				}
			}
			else{
				$is_server_free_at_first = self::getSingalFreeServerOnSpecificTime($server,$datetime);
				$max_service_period = $server->services->where('description','<>','特殊服務')->where('status','<>','hide')->sortByDesc('period')->first()->period;
				//這是在看該時段能否塞下最長服務方案，如果可以，那就搞只需要輸出一條訊息
				$available->abletime = date('Hi',strtotime($datetime));
				$available->datetime = $datetime;
				$available->periods = array_unique($server->services->where('description','<>','特殊服務')->where('status','<>','hide')->sortBy('period')->pluck('period')->all());
				$temp_datetime2 = date('Hi',strtotime($datetime));
				if (substr($temp_datetime2,0,2)=='00'){
					$temp_datetime2 = '24'.substr($temp_datetime2,2,2);
				}
				$available->message = $server->name.$temp_datetime2.'可';


				if ( (count($is_server_free_at_first->periods)>0) && max($is_server_free_at_first->periods) == $max_service_period ){
					$available->is_all_period_able = true;
					return $available;
				}
				else{
					foreach( array_unique($is_server_free_at_first->periods) as $period ){
						$available->message .= $period;
						if ($period!=end($is_server_free_at_first->periods)){
							$available->message .= '/';
						}
						else{
							$available->message .= "\n";
						}
					}
					//先找到下一個可以的時間


					//這指的是找到能塞入最大服務時常的空白時段
					$bool_find_max_ok_time = false;
					$not_ok_schedule_time = date('Y-m-d H:i:00',strtotime($datetime));
					while(!$bool_find_max_ok_time){
						$schedule1 = ScheduleUnit::where('start_time','>=',$not_ok_schedule_time)
						->where('start_time','<=',$work_end_time)
						->where('server_id',$server->id)
						->whereNull('booking_id')
						->first();
						if (!$schedule1){
							echo '下班前已無可排入全方案之空白時段';
							$available->message .= $server->name.'其他已滿';
							return $available;
						}
						//上面時間抓到的第一個空白，先來看看他能不能塞入最長時間服務，如果連塞最長時間服務都會超出下班時間，那就break
						$end_timestamp1  = strtotime($schedule1->start_time)+$max_service_period*60;
						if ($end_timestamp1>$work_end_timestamp){
							break;
						}
						$maybe_ok_schedule1 = ScheduleUnit::where('start_time','>=',$schedule1->start_time)
						->where('start_time','<=',date('Y-m-d H:i:s',$end_timestamp1))
						->where('server_id',$server->id)
						->whereNotNull('booking_id')
						->first();
						if ($maybe_ok_schedule1){
							$not_ok_schedule_time = $maybe_ok_schedule1->start_time;
							continue;
						}
						$bool_find_max_ok_time = true;
					}
					if (!$bool_find_max_ok_time){
						echo '該服務員後續已無可預約時間';
						$available->message .= '已滿';
						return $available;
					}

					$temp_datetime3 = date('Hi',strtotime($schedule1->start_time));
					if (substr($temp_datetime3,0,2)=='00'){
						$temp_datetime3 = '24'.substr($temp_datetime3,2,2);
					}
					$available->message .= $server->name.$temp_datetime3.'可';
					// foreach( $available->periods as $period ){
					// 	$available->message .= $period;
					// 	if ($period!=end($available->periods)){
					// 		$available->message .= '/';
					// 	}
					// 	else{
					// 		$available->message .= "\n";
					// 	}
					// }
					return $available;
				}
			}
	

		return '意料外的情況';
	}

	public static function getServerNextFreeTime1($server, $datetime=null){
		$is_server_not_work_at_datetime = false;
		if ( strtotime($datetime)<strtotime($server->start_time)){
			//今天有班，但查詢的時間還沒到服務員上班時間
			$is_server_not_work_at_datetime = true;
			$datetime = self::timeFormate($server->start_time);
		}

		// $work_start_timestamp = strtotime($server->start_time);
		$work_end_timestamp = strtotime($server->end_time);
		
		// $work_start_time = date('Y-m-d H:i:s', $work_start_timestamp);
		$work_end_time = date('Y-m-d H:i:s', $work_end_timestamp);
		
		$datetime = $datetime ?? date('Y-m-d H:i:s');
		
		$message = '';
		if( strtotime($datetime)>$work_end_timestamp ){
			$message = "查詢時間已超過".$server->name."服務時間(".date('m-d H:i',$work_end_timestamp).")";
			return (object)[
				'abletime' => null,
				'datetime' => null,
				'periods' => [],
				'message' => $message,
			];
		}

		$available = (object)[
			'abletime' => null,
			'datetime' => $datetime,
			'is_all_period_able' => false,
			'periods' => '',
			'message' => '',
		];

		$min_service_period = $server->services->where('description','<>','特殊服務')->where('status','<>','hide')->sortBy('period')->first()->period;

		$datetime_timestamp = strtotime($datetime);
		$is_find_closed_ok_time = false;

		//自想查詢的目標時間開始，加上最短服務時間後看是不是都有空
			$end_timestamp = $datetime_timestamp+$min_service_period*60;
	
			$target_schedule = ScheduleUnit::whereBetween('start_time',[$datetime,date('Y-m-d H:i:s',$end_timestamp)])
			->orWhere(function($query) use ($datetime,$end_timestamp)
			{
					$query->whereBetween('end_time',[$datetime,date('Y-m-d H:i:s',$end_timestamp)]);
			})
			->where('server_id',$server->id)
			->whereNotNull('booking_id')
			->orderBy('start_time')
			->first();

			//進去表示當前時間連最短方案都沒空
			if ($target_schedule){
				//接著要試著找到至少最短方案可以的時間
				$bool_find_ok_time = false;
				$not_ok_schedule_time = $target_schedule->start_time;
				while(!$bool_find_ok_time){
					//這裡要從上面抓到的不行的時間繼續往下抓，找到空白的時間再來檢驗，可是如果到下班前都沒有空白，就break出迴圈
					$schedule = ScheduleUnit::where('start_time','>=',$not_ok_schedule_time)
					->where('start_time','<=',$work_end_time)
					->where('server_id',$server->id)
					->whereNull('booking_id')
					->first();
					
					if (!$schedule){
						break;
					}
					
					//上面時間抓到的第一個空白，先來看看他能不能塞入最短時間服務，如果連塞最短時間服務都會超出下班時間，那就break
					$end_timestamp  = strtotime($schedule->start_time)+$min_service_period*60;
					if ($end_timestamp>$work_end_timestamp){
						break;
					}
					$maybe_ok_schedule = ScheduleUnit::where('start_time','>=',$schedule->start_time)
					->where('end_time','<=',date('Y-m-d H:i:s',$end_timestamp))
					->where('server_id',$server->id)
					->whereNotNull('booking_id')
					->first();

					if ($maybe_ok_schedule){
						$not_ok_schedule_time = $maybe_ok_schedule->start_time;
						continue;
					}
					
					$bool_find_ok_time = true;
					//能到這邊表示已經找到可以塞入最小方案的空白時段，所以要驗證該空白能否塞下更長的方案，如果可以塞下全方案，就皆大歡喜

					$is_server_free = self::getSingalFreeServerOnSpecificTime($server,$schedule->start_time);
					$max_service_period = $server->services->where('description','<>','特殊服務')->where('status','<>','hide')->sortByDesc('period')->first()->period;
					//這是在看該時段能否塞下最長服務方案，如果可以，那就搞只需要輸出一條訊息
					$available->abletime = date('Hi',strtotime($schedule->start_time));
					$available->datetime = $schedule->start_time;
					$available->periods = array_unique($is_server_free->periods);
					$temp_datetime = date('Hi',strtotime($schedule->start_time));
					if (substr($temp_datetime,0,2)=='00'){
						$temp_datetime = '24'.substr($temp_datetime,2,2);
					}
					$available->message .= $server->name.$temp_datetime.'可';
					$index = 1;
					foreach( $available->periods as $period ){
						$available->message .= $period;
						if ($index!=count($available->periods)){
							$available->message .= '/';
						}
						else{
							$available->message .= "\n";
						}
						$index ++;
					}
					if ( max($is_server_free->periods) == $max_service_period ){
						return $available;
					}
					else{
						//這指的是找到能塞入最大服務時常的空白時段
						$bool_find_max_ok_time = false;
						$not_ok_schedule_time = date('Y-m-d H:i:00',strtotime($schedule->start_time));
						while(!$bool_find_max_ok_time){
							$schedule1 = ScheduleUnit::where('start_time','>=',$not_ok_schedule_time)
							->where('start_time','<=',$work_end_time)
							->where('server_id',$server->id)
							->whereNull('booking_id')
							->first();
							if (!$schedule1){
								echo '下班前已無可排入全方案之空白時段';
								$available->message .= $server->name.'其他已滿';
								return $available;
							}
							//上面時間抓到的第一個空白，先來看看他能不能塞入最長時間服務，如果連塞最長時間服務都會超出下班時間，那就break
							$end_timestamp1  = strtotime($schedule1->start_time)+$max_service_period*60;
							if ($end_timestamp1>$work_end_timestamp){
								break;
							}
							$maybe_ok_schedule1 = ScheduleUnit::where('start_time','>=',$schedule1->start_time)
							->where('start_time','<=',date('Y-m-d H:i:s',$end_timestamp1))
							->where('server_id',$server->id)
							->whereNotNull('booking_id')
							->first();
							if ($maybe_ok_schedule1){
								$not_ok_schedule_time = $maybe_ok_schedule1->start_time;
								continue;
							}
							$bool_find_max_ok_time = true;
						}
						if (!$bool_find_max_ok_time){
							break;
						}

						$temp_datetime1 = date('Hi',strtotime($schedule1->start_time));
						if (substr($temp_datetime1,0,2)=='00'){
							$temp_datetime1 = '24'.substr($temp_datetime1,2,2);
						}
						$available->message .= $server->name.$temp_datetime1.'可';
						$periods = array_unique($server->services->where('description','<>','特殊服務')->where('status','<>','hide')->sortBy('period')->pluck('period')->all());
						$index = 1;
						foreach( $periods as $period ){
							$available->message .= $period;
							if ($index!=count($periods)){
								$available->message .= '/';
							}
							$index ++;
						}
						return $available;
					}
				}
				if (!$bool_find_ok_time){
					echo '該服務員後續已無可預約時間';
					$available->message = $server->name.'已滿';
					return $available;
				}
				else if (!$bool_find_max_ok_time){
					echo '該服務員後續已無可放入全方案時段';
					$available->message .= $server->name.'其他已滿';
					return $available;
				}
			}
			else{
				$is_server_free_at_first = self::getSingalFreeServerOnSpecificTime($server,$datetime);
				$max_service_period = $server->services->where('description','<>','特殊服務')->where('status','<>','hide')->sortByDesc('period')->first()->period;
				//這是在看該時段能否塞下最長服務方案，如果可以，那就搞只需要輸出一條訊息
				$available->abletime = date('Hi',strtotime($datetime));
				$available->datetime = $datetime;
				$available->periods = array_unique($server->services->where('description','<>','特殊服務')->where('status','<>','hide')->sortBy('period')->pluck('period')->all());
				$temp_datetime2 = date('Hi',strtotime($datetime));
				if (substr($temp_datetime2,0,2)=='00'){
					$temp_datetime2 = '24'.substr($temp_datetime2,2,2);
				}
				$available->message = $server->name.$temp_datetime2.'可';

				foreach( array_unique($is_server_free_at_first->periods) as $period ){
					$available->message .= $period;
					if ($period!=end($is_server_free_at_first->periods)){
						$available->message .= '/';
					}
					else{
						$available->message .= "\n";
					}
				}
				if ( (count($is_server_free_at_first->periods)>0) && max($is_server_free_at_first->periods) == $max_service_period ){
					$available->is_all_period_able = true;
					return $available;
				}
				else{
	
					//先找到下一個可以的時間


					//這指的是找到能塞入最大服務時常的空白時段
					$bool_find_max_ok_time = false;
					$not_ok_schedule_time = date('Y-m-d H:i:00',strtotime($datetime));
					while(!$bool_find_max_ok_time){
						$schedule1 = ScheduleUnit::where('start_time','>=',$not_ok_schedule_time)
						->where('start_time','<=',$work_end_time)
						->where('server_id',$server->id)
						->whereNull('booking_id')
						->first();
						if (!$schedule1){
							echo '下班前已無可排入全方案之空白時段';
							$available->message .= $server->name.'其他已滿';
							return $available;
						}
						//上面時間抓到的第一個空白，先來看看他能不能塞入最長時間服務，如果連塞最長時間服務都會超出下班時間，那就break
						$end_timestamp1  = strtotime($schedule1->start_time)+$max_service_period*60;
						if ($end_timestamp1>$work_end_timestamp){
							break;
						}
						$maybe_ok_schedule1 = ScheduleUnit::where('start_time','>=',$schedule1->start_time)
						->where('start_time','<=',date('Y-m-d H:i:s',$end_timestamp1))
						->where('server_id',$server->id)
						->whereNotNull('booking_id')
						->first();
						if ($maybe_ok_schedule1){
							$not_ok_schedule_time = $maybe_ok_schedule1->start_time;
							continue;
						}
						$bool_find_max_ok_time = true;
					}
					if (!$bool_find_max_ok_time){
						echo '該服務員後續已無可預約時間';
						$available->message .= '已滿';
						return $available;
					}

					$temp_datetime3 = date('Hi',strtotime($schedule1->start_time));
					if (substr($temp_datetime3,0,2)=='00'){
						$temp_datetime3 = '24'.substr($temp_datetime3,2,2);
					}
					$available->message .= $server->name.$temp_datetime3.'可';
					foreach( $available->periods as $period ){
						$available->message .= $period;
						if ($period!=end($available->periods)){
							$available->message .= '/';
						}
						else{
							$available->message .= "\n";
						}
					}
					return $available;
				}
			}
	

		return '意料外的情況';
	}

	public static function searchDailyGroupSchedule($group_id,$search_datetime=null,$end_search_datetime=null){
		$message = '';
		$message = self::searchDailyGroupSchedule2($group_id,$search_datetime,$end_search_datetime);
		
		if ( isset($search_datetime) && isset($end_search_datetime) ){
			$search_datetime2 = date('Y-m-d H:i:s',strtotime($search_datetime)+86400);
			$end_search_datetime2 = date('Y-m-d H:i:s',strtotime($end_search_datetime)+86400);
			$result = self::searchDailyGroupSchedule2($group_id,$search_datetime2,$end_search_datetime2);
			if (!empty($result)){
				if (!empty($message)){
					$message .= "\n"."\n"."\n";
				}
				$message .= $result;
			}
		}
		else{
			$split_hour = config('app.system.day_split_hour');
			$split_hour_string = str_pad($split_hour,2,'0',STR_PAD_LEFT);
			if ( empty($search_datetime) ){
				$search_datetime = date("Y-m-d {$split_hour_string}:00:00");
				// hour小於指定時間,表示目前在當日工作時間中,回到該工作日的開始時間
				if ( date('H')<$split_hour ){
					$search_datetime = date("Y-m-d {$split_hour_string}:00:00", strtotime('-1 day'));
				}
			}
			if ( empty($end_search_datetime) ){
				$end_search_datetime = date("Y-m-d {$split_hour_string}:00:00", strtotime('+1 day'));
				// hour小於指定時間,表示目前在當日工作時間中,本日的時間
				if ( date('H')<$split_hour ){
					$end_search_datetime = date("Y-m-d {$split_hour_string}:00:00");
				}
			}
			$search_datetime2 = date('Y-m-d H:i:s',strtotime($search_datetime)+86400);
			$end_search_datetime2 = date('Y-m-d H:i:s',strtotime($end_search_datetime)+86400);

			$result = self::searchDailyGroupSchedule2($group_id,$search_datetime2,$end_search_datetime2);
			if (!empty($result)){
				if (!empty($message)){
					$message .= "\n"."\n"."\n";
				}
				$message .= $result;
			}

		}
		return $message;
	}
	
	//for endwork use
	public static function searchDailyGroupSchedule2($group_id,$search_datetime=null,$end_search_datetime=null){
		// 列出所有在本群所屬合作廠商的指定服務員
		//當日行程時間範圍=> T:07:00~T+1:07:00
		$split_hour = config('app.system.day_split_hour');
		$split_hour_string = str_pad($split_hour,2,'0',STR_PAD_LEFT);
		if ( empty($search_datetime) ){
			$search_datetime = date("Y-m-d {$split_hour_string}:00:00");
			// hour小於指定時間,表示目前在當日工作時間中,回到該工作日的開始時間
			if ( date('H')<$split_hour ){
				$search_datetime = date("Y-m-d {$split_hour_string}:00:00", strtotime('-1 day'));
			}
		}
		if ( empty($end_search_datetime) ){
			$end_search_datetime = date("Y-m-d {$split_hour_string}:00:00", strtotime('+1 day'));
			// hour小於指定時間,表示目前在當日工作時間中,本日的時間
			if ( date('H')<$split_hour ){
				$end_search_datetime = date("Y-m-d {$split_hour_string}:00:00");
			}
		}

		if ( strtotime($search_datetime) < strtotime($end_search_datetime) ){
			$server = Server::where('line_group_id',$group_id)->first();

			$bookings = Booking::where('status','<>','Cancel')
				->where('server_id',$server->id)
				->where('start_time','>=',$search_datetime)
				->Where('start_time','<=',$end_search_datetime)
				->orderBy('start_time')
				->get();
			
			if ( count($bookings)>0 ){
				$date_array =[];
				$msg = date('m/d',strtotime($search_datetime))."\n";
				foreach ( $bookings as $booking ){
					//休息的部分，優先判斷處理
					if ( $booking->status == 'Rest' ){
						$start_time = date("H:i",strtotime($booking->start_time));
						if ( date("H",strtotime($booking->start_time))=='00'){
							$start_time=date("24:i",strtotime($booking->start_time));
						}
						$end_time = date("H:i",strtotime($booking->end_time));
						if ( date("H",strtotime($booking->end_time))=='00'){
							$end_time=date("24:i",strtotime($booking->end_time));
						}
						$msg .= $start_time.'~'.$end_time.'休息'."\n";
						continue;
					}

					$group_admin = GroupAdmin::where('nickname',$booking->admin_nickname)->first();
					if (!$group_admin){
						$temp_group_admin = temp_group_admin::where('nickname',$booking->admin_nickname)->first();
						if (!$temp_group_admin){
							Log::error('錯誤，有不存在的管理員暱稱!');
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
				return null;
			}
		}
		else {return false;}
	}


	//這是班表反解析用的，因為這邊不是要返回給下定群
	public static function searchDailyGroupBooking($group_id,$search_datetime=null,$end_search_datetime=null){
		// 列出所有在本群所屬合作廠商的指定服務員
		//當日行程時間範圍=> T:07:00~T+1:07:00
		$split_hour = config('app.system.day_split_hour');
		$split_hour_string = str_pad($split_hour,2,'0',STR_PAD_LEFT);
		if ( empty($search_datetime) ){
			$search_datetime = date("Y-m-d {$split_hour_string}:00:00");
			// hour小於指定時間,表示目前在當日工作時間中,回到該工作日的開始時間
			if ( date('H')<$split_hour ){
				$search_datetime = date("Y-m-d {$split_hour_string}:00:00", strtotime('-1 day'));
			}
		}
		if ( empty($end_search_datetime) ){
			$end_search_datetime = date("Y-m-d {$split_hour_string}:00:00", strtotime('+1 day'));
			// hour小於指定時間,表示目前在當日工作時間中,本日的時間
			if ( date('H')<$split_hour ){
				$end_search_datetime = date("Y-m-d {$split_hour_string}:00:00");
			}
		}

		if ( strtotime($search_datetime) < strtotime($end_search_datetime) ){
			$server_id = Server::where('line_group_id',$group_id)->pluck('id');
			//此處note為班表資訊
			$booking = Booking::where('server_id',$server_id)
			->where('start_time','>=',$search_datetime)
			->where('start_time','<=',$end_search_datetime)
		  ->where('status','!=','Cancel')
			->orderBy('start_time')
			->get();
				

			if ( $booking ){
				 return $booking;
			}
			else{

				 return [];
			}
		}
		else {return false;}
	}

	//學長你這裡要調整，不適每個服務員都一定有短服務跟長服務
	public static function getSingalFreeServerOnSpecificTime($server, $search_datetime){
		$message = '';
    $result = (object)[
			'available' => false,
			'wait' => null,
			'periods' => [],
			'message' => "{$server->name} 當下不可",
		];
		$services = Service::where('server_id',$server->id)->where('description','!=','特殊服務')->where('period','!=',0)->where('status','<>','hide')->orderBy('period','Desc')->get();
		if ( count($services)==0 ){
			$result->message = "{$server->name} 並沒有任何可用方案";
			return $result;
		}
		$max_ok_period = 0;
		//因為要知道當下是否有空，而時間是以5分進位的，所以做此處理
		$formate_minutes = intval(date('i',strtotime($search_datetime))/5)*5;
		if ((date('i',strtotime($search_datetime))%5)!=0){
			$formate_minutes = (intval(date('i',strtotime($search_datetime))/5))*5;
		}
		if ( $formate_minutes < 10 ){
			$formate_minutes = '0'.$formate_minutes;
		}
		$search_datetime = date('Y-m-d H:',strtotime($search_datetime)).$formate_minutes.':00';

		if ( strtotime($server->start_time)>strtotime($search_datetime)){
			$result->message = "{$server->name} 於該時段尚未上班";
			return $result;
		}
		if ( strtotime($server->end_time)<strtotime($search_datetime)){
			$result->message = "{$server->name} 於該時段尚未上班";
			return $result;
		}

		foreach ( $services as $service ){
			$end_time = self::timeformate(date('Y-m-d H:i:00',strtotime($search_datetime)+$service->period*60));
			$schedule_units = ScheduleUnit::where('start_time','>=',$search_datetime)->where('end_time','<=',$end_time)->where('server_id',$server->id)->where('booking_id','!=','null')->get();
			if (count($schedule_units)!=0){
				continue;
			}
			$server = Server::where('id',$server->id)->first();
			if ( empty($server->end_time) ){
				if (empty($server->start_time)){
					continue;
				}
			}
			else{
				if ( strtotime($end_time) > strtotime($server->end_time) ){
					continue;
				}
			}
			$max_ok_period = $service->period;
			break;
		}

		if ($max_ok_period==0){
			return $result;
		}
		$ok_services = Service::where('server_id',$server->id)->where('description','!=','特殊服務')->where('period','<=',$max_ok_period)->where('period','!=',0)->where('status','<>','hide')->orderBy('period','Asc')->get();
		foreach ( $ok_services as $ok_service ){
			$price = 0;
			$price=$ok_service->server_fee
			+$ok_service->broker_fee
			-$ok_service->company_cost
			+$ok_service->company_profit
			-$ok_service->marketing_cost
			+$ok_service->sales_profit;
			$message .= $ok_service->s_time.'/'.$ok_service->period.'/'.'回'.$price."\n";
			$result->periods[] = $ok_service->period;
		}
		$result->available = true;
		$result->message = $message;
		return $result;
	}

	public static function scheduleUnitSeeds($begin_time,$server_id){
		$begin_time=self::timeFormate($begin_time);
		$begin_time=strtotime($begin_time);
		$end_time=$begin_time+86400*3-300;

		// $server = Server::find($server_id);
		// $server->start_time = date('Y-m-d H:i:s',$begin_time);
		// $server->end_time = date('Y-m-d H:i:s',$end_time);
		// $server->save();

		while($end_time>$begin_time){
			$result = ScheduleUnit::updateOrCreate(
				['start_time'=>date('Y-m-d H:i:s',$begin_time),'server_id'=>$server_id],
				['end_time'=>date('Y-m-d H:i:s',$begin_time+300)]
			);
			$begin_time += 300;
		}
		return (object)['start_time'=>$begin_time,'end_time'=>date('Y-m-d H:i:s',$end_time)];
	}

	public static function timeformate($time){
		$day = array();
		$his = array();
		$day = explode(' ',$time);
		$date = $day[0];
		$his = explode(':',$day[1]);
		$hour = $his[0];
		$minute = $his[1];
		$second = $his[2];
		
		$next_unit_minute = floor($minute/5)*5;
		if( $minute%5>0 )
			$next_unit_minute += 5;
		
		//如果分尾數是0/5 就不動
		if ( (5-$minute%5)!=0 ){
			//分往後推如果變成60 就進位，如果時為23則+一天
			if ( $hour==23 && $next_unit_minute==60 ){
				$hour = '00';
				$minute = '00';
				$date = date('Y-m-d',strtotime($date)+86400);
			}
			else if($next_unit_minute==60){
				$hour = $hour+1;
				if( $hour<10 ){
					$hour = '0'.$hour;
				}
				$minute = '00';
			}
			else{
				$minute = $next_unit_minute;
			}
		}
		if (  strlen($minute)==1  ){
			$minute = '0'.$minute;
		}
		return $date.' '.$hour.':'.$minute.':00';
	}

	public static function getWorkDayTime(){
		$result = (object)[];
		$split_hour = config('app.system.day_split_hour');
		$split_hour_string = str_pad($split_hour,2,'0',STR_PAD_LEFT);
		$search_datetime = date("Y-m-d {$split_hour_string}:00:00");
		// hour小於指定時間,表示目前在當日工作時間中,回到該工作日的開始時間
		if ( date('H')<$split_hour ){
			$search_datetime = date("Y-m-d {$split_hour_string}:00:00", strtotime('-1 day'));
		}
		$end_search_datetime = date("Y-m-d {$split_hour_string}:00:00", strtotime('+1 day'));
		// hour小於指定時間,表示目前在當日工作時間中,本日的時間
		if ( date('H')<$split_hour ){
			$end_search_datetime = date("Y-m-d {$split_hour_string}:00:00");
		}

		$result->date = date('Y-m-d',strtotime($search_datetime));
		$result->start_time = $search_datetime;
		$result->end_time = $end_search_datetime;
		return $result;
	}

	public static function lockRedis($redis_name,$lock_time=null){
			Redis::hmset($redis_name,'redis_lock',true,'lock_time',time());
	}
	public static function unlockRedis($redis_name){
		Redis::hdel($redis_name,'redis_lock');
		Redis::hdel($redis_name,'lock_time');
	}
	public static function getServerAndServicePointPhoto($server){

		$server_img_pairs = ServerImgPair::where('server_id',$server->id)->offset(0)->limit(2)->get();
    $messages = [];
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

    $room_server_pair = RoomServerPair::where('server_id',$server->id)->first();
    if ( !$room_server_pair ){
      return $messages;
    }
    $room_data = RoomData::where('id',$room_server_pair->room_data_id)->first();
    $service_point_img_pairs = ServicePointImgPair::where('service_point_id',$room_data->service_point_id)->offset(0)->limit(2)->get();
    foreach (  $service_point_img_pairs as  $service_point_img_pair ){
      $object = (object)[];
      $object->type = 'image';
      $image_url = Image::where("id",$service_point_img_pair->image_id)->first()->image_url;
      $image_data = [];
      $image_data = explode("/",$image_url);
      $url = Storage::disk($image_data[0])->url($image_data[1]);
      $object->originalContentUrl = $url;
      $object->previewImageUrl = $url;
      $messages[] = $object;
    }
		if ( count($service_point_img_pairs)==0){
			$messages[] = '該服務員尚未設訂約客地照片，請聯繫總機要照';
		}


    return $messages;
	}
	public static function getServicePointPhoto($server){

    $room_server_pair = RoomServerPair::where('server_id',$server->id)->first();
    if ( !$room_server_pair ){
      return $messages;
    }
    $room_data = RoomData::where('id',$room_server_pair->room_data_id)->first();
    $service_point_img_pairs = ServicePointImgPair::where('service_point_id',$room_data->service_point_id)->offset(0)->limit(2)->get();
    foreach (  $service_point_img_pairs as  $service_point_img_pair ){
      $object = (object)[];
      $object->type = 'image';
      $image_url = Image::where("id",$service_point_img_pair->image_id)->first()->image_url;
      $image_data = [];
      $image_data = explode("/",$image_url);
      $url = Storage::disk($image_data[0])->url($image_data[1]);
      $object->originalContentUrl = $url;
      $object->previewImageUrl = $url;
      $messages[] = $object;
    }


    return $messages;
	}

	public static function getRoomPhoto($server){

    $room_server_pair = RoomServerPair::where('server_id',$server->id)->first();
    if ( !$room_server_pair ){
      return $messages;
    }

    $room_img_pairs = RoomImgPair::where("room_data_id",$room_server_pair->room_data_id)->offset(0)->limit(4)->get();
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
	public static function sortServerByServicePointAndCountry($servers){
		$tmp = [];
		foreach( $servers as $server ){
			$is_server_has_a_room = RoomServerPair::where('server_id',$server->id)->first();
			if ( $is_server_has_a_room ){
					$room_data = RoomData::where('id',$is_server_has_a_room->room_data_id)->first();
					$service_point = ServicePoint::where('id',$room_data->service_point_id)->first();
					$tmp[$server->id]=$service_point->id;
			}
			else{
				$tmp[$server->id]=999998;
			}
		}
		asort($tmp);
		$key_array = [];
		$key_array = array_keys($tmp);
		$server_datas = Server::whereIn('id',$key_array)->orderByRaw(DB::raw('FIND_IN_SET(id, "' . implode(",", $key_array) . '"' . ")"))->orderBy('country_id','asc')->get();
		return $server_datas;
	}

	public static function getServerServicePoint($server){
		$result	= (object)[];
		if( $server->enable == 'N' ){
			$service_point = ServicePoint::where('id',999999)->first();
			if (!$service_point){
				$area = Area::where('id',999999)->first();
				if (!$area){
					$area = new Area;
					$area->id = 999999;
					$area->name = '下架';
					$area->save();
				}
				$service_point = new ServicePoint;
				$service_point->id = 999999;
				$service_point->area_id = 999999;
				$service_point->nickname = '下';
				$service_point->name = '下架';
				$service_point->save();
			}
			$result->id = $service_point->id;
			$result->name = $service_point->name;
		}
		else{
			$is_server_has_a_room = RoomServerPair::where('server_id',$server->id)->first();
			if( $is_server_has_a_room ){
					$room_data = RoomData::where('id',$is_server_has_a_room->room_data_id)->first();
					$service_point = ServicePoint::where('id',$room_data->service_point_id)->first();
					if( $service_point ){
						$result->id = $service_point->id;
						$result->name = $service_point->name;
					}
			}
			else{
				$service_point = ServicePoint::where('id',999998)->first();
				if (!$service_point){
					$area = Area::where('id',999998)->first();
					if (!$area){
						$area = new Area;
						$area->id = 999998;
						$area->name = '未分類';
						$area->save();
					}
					$service_point = new ServicePoint;
					$service_point->id = 999998;
					$service_point->area_id = 999998;
					$service_point->name = '未分類';
					$service_point->nickname = '未';
					$service_point->save();
				}
				$result->id = $service_point->id;
				$result->name = $service_point->name;
			}

		}
		return $result;
	}



}