<?php
namespace App\Command;

use DB;
use Session;
use App\Model\PartnerSalesAuth;
use App\Model\Booking;
use App\Model\Service;
use App\Model\Sales;
use App\Model\Server;
use App\Model\GroupAdmin;
use App\Model\PartnerGroupPair;
use App\Model\ScheduleUnit;
use App\Model\temp_group_admin;

class ScheduleParsing extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new ScheduleParsing();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '更新班表',
			'cmd' => '班表',
			'description' => '將群組得到的班表資訊反解析，並更新上系統',
			'args' => [
				'partner_id'
			],
			'access' => ['admin','group_admin','temp_group_admin'],
			'authorized_group_type' => ['Server'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ){
		$message = "";
		$user = $args->user;
		$group = $args->group;

		if (function_exists("fastcgi_finish_request")) { 
			fastcgi_finish_request();
		}
		// 沒有棒定服務員,不做事
		if ( !$group->server ){
			return config('app.debug') ? '服務群未綁定服務員' : null;
		}

		if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return config('app.debug') ? $message : null;
		}
		
		$command = $args->command;

		$partner_id_array = [];
		$partner_group_pair = PartnerGroupPair::where('line_group_id',$group->id)->where('group_type','Server')->get();

		foreach ( $partner_group_pair as $pair ){
			$partner_id_array[] = $pair->partner_id;
		}
		$if_server_start_work = Server::where('id',$group->server->id)->first();
		//上班時間如果小於今天，表示上班時間是昨天，所以要更新
		if (date('H')>7) {
			CommandUtil::scheduleUnitSeeds(date('Y-m-d 07:i:s'),$group->server->id);
		}
		else{
			CommandUtil::scheduleUnitSeeds(date('Y-m-d 07:i:s',strtotime('-1 day')),$group->server->id);
		}
		//考慮到要一次輸入兩天，就從這裡開始作手腳
		$command = mb_substr($command,mb_strlen($this->command_data['cmd']));
		$input_date_schedule_array = $this->checkTommorowScheduleExist($command);
		
		$rest_result_message = '';
		$cancel_message ='';

		$canceled_array = [];
		foreach ( $input_date_schedule_array as $index => $date_value ){
			$return_array = [];
				$command = '';

				foreach( $date_value as $break_line ){
					$command .= $break_line."\n";
				}

				$date = $this->getScheduleDate($command);
				// $work_time_result = CommandUtil::getWorkDayTime();
				$split_hour = config('app.system.day_split_hour');
				$split_hour_string = str_pad($split_hour,2,'0',STR_PAD_LEFT);
				$search_datetime = date("Y-m-d {$split_hour_string}:00:00",strtotime($date));
				$end_search_datetime = date("Y-m-d {$split_hour_string}:00:00", strtotime($date)+86400);

				$daily_bookings = CommandUtil::searchDailyGroupBooking($group->server->line_group_id,$search_datetime,$end_search_datetime);
				//這裡要先執行預刪除，將班表內所有資料全部刪除光
				$delete_result = $this->deleteAllSchedule($group->server,$daily_bookings,$date_value);
				if (!$delete_result->result){
					return $delete_result->message;
				}

				$daily_bookings = CommandUtil::searchDailyGroupBooking($group->server->line_group_id,$search_datetime,$end_search_datetime);
				$daily_booking_start_time = [];

				foreach ( $daily_bookings as $daily_booking ){
					if ( $daily_booking->is_pre_booking=='N' ){
						$daily_booking_start_time[] = $daily_booking->start_time;
					}
				}


				$parsing_results = $this->scheduleParsing($command,$group->server,$partner_id_array);
		
				if ( !$parsing_results->is_legal ){
					if ( isset($parsing_results->after_cancel) ){
						if (isset($parsing_results->canceled_array) && count($parsing_results->canceled_array)>0){
							foreach($parsing_results->canceled_array as $value){
								$canceled_array[]=$value;
							}
							// if ( count($canceled_array)>0 ){
							// 	if (count($input_date_schedule_array)>1){
							// 		$cancel_message .= $date_value[0].'-'.'訂單取消成功:'."\n";
							// 	}
							// 	else{
							// 		$cancel_message .= '訂單取消成功:'."\n";
							// 	}
							// 	foreach( $canceled_array as $value){
							// 		$cancel_message .= $value."\n";
							// 	}
							// }

						}				
						continue;
					}
					else{
						if (count($input_date_schedule_array)>1){
							return $date_value[0].'-'.$parsing_results->message;
						}
						else{
							return $parsing_results->message;
						}	
					}
				}


				$parsing_results_array = $parsing_results->schedule_parsing_result;
				// print_r($parsing_results_array);exit;
				foreach( $parsing_results->schedule_parsing_result as $_result ){
					//這裡是要看拆解後的班表，跟資料庫撈出來的班表比較，如果傳進來的沒有，而資料庫的有就表示該筆要做刪除
					//我上面把資料庫撈的班表時間存在 daily_booking_start_time 這個陣列裡面，所以這邊只要解析班表的time in arrary，那就把該項刪掉，這樣最後arrray剩下的，勢必就是被刪掉的
					if ( in_array($_result->input_time, $daily_booking_start_time) ){
						array_splice($daily_booking_start_time, array_search($_result->input_time,$daily_booking_start_time), 1);
					}
				} 

				foreach ( $daily_booking_start_time as $value ){

					// if (date('H')<config('app.system.day_split_hour')){
					// 	if (strtotime($value)>strtotime(date('Y-m-d').' '.config('app.system.day_split_hour').':00:00')){
					// 		continue;
					// 	}
					// }
					// else{
					// 	if (strtotime($value)>strtotime(date('Y-m-d',strtotime("+1 day")).' '.config('app.system.day_split_hour').':00:00')){
					// 		continue;
					// 	}
					// }

					$result = $this->cancelSchedule($value,$group->server);
					if ( !$result->result ){
						if (count($input_date_schedule_array)>1){
							return $date_value[0].'-'.$result->message;
						}
						else{
							return $result->message;
						}	
					}
					// else{
					// 	if (count($input_date_schedule_array)>1){
					// 		$cancel_message .= $date_value[0].'-'.$result->message;
					// 	}
					// 	else{
					// 		$cancel_message .= $result->message;
					// 	}	

					// 	if ( count($canceled_array)>0 ){
					// 		if (count($input_date_schedule_array)>1){
					// 			$cancel_message .= $date_value[0].'-'.'訂單取消成功:'."\n";
					// 		}
					// 		else{
					// 			$cancel_message .= '訂單取消成功:'."\n";
					// 		}
					// 		foreach( $canceled_array as $value){
					// 			$cancel_message .= $value."\n";
					// 		}
					// 	}
					// }
					if (isset($result->cancel_time)){
						$canceled_array[] = $result->cancel_time;
					}
				}

				foreach( $parsing_results->schedule_parsing_result as $_result ){
					if ($_result->status == 'nochange'){
						continue;
					}
					if ($_result->status == 'insert'){
						$result = $this->insertSchedule($user,$group->id,$_result->sales_id,$_result->target_service_id,$group->server,$_result->input_time,$_result);
						// if ( $result->result == false ){
						// 	return $result->message;
						// }
					}
					if ($_result->status == 'update'){
						$result = $this->updateSchedule($user,$group->id,$_result->booking_id,$_result->sales_id,$_result->target_service_id,$group->server,$_result->input_time,$_result->input_price,$_result);
						// if ( $result->result == false ){
						// 	return $result->message;
						// }
					}
					
				} 

				//這邊就是處理完的最新班表，最後再來處理休息的部分
				if (isset($parsing_results->rest_time_array)){
		
					$rest_time_array = $parsing_results->rest_time_array;
					foreach ( $rest_time_array as $rest_time ){
						$time_gap_count = intval((strtotime($rest_time->end_time)-strtotime($rest_time->start_time))/300);
						$schedule_units = ScheduleUnit::where('server_id',$group->server->id)->where('start_time','>=',$rest_time->start_time)->where('end_time','<=',$rest_time->end_time)->lockForUpdate()->get();
						if ( count($schedule_units)<$time_gap_count ){
							$rest_result_message .= date('H:i',strtotime($rest_time->start_time)).'~'.date('H:i',strtotime($rest_time->end_time)).'之休息時段，該服務員班表沒空'."\n";
						}
						$not_free_schedule = $schedule_units->whereNotNull('booking_id')->first();
						if ($not_free_schedule){
							$rest_booking = Booking::where('id',$not_free_schedule->booking_id)->first();
							// if ($rest_booking->status == 'Rest' && $rest_time->start_time==$rest_booking->start_time && $rest_time->end_time==$rest_booking->end_time ){
							// 	continue;
							// }
							// else{
								if ( $rest_booking->status == 'Rest' ){
									$not_free_schedules = DB::table('schedule_units')
									->join('bookings','schedule_units.booking_id','=','bookings.id')
									->where('schedule_units.server_id',$group->server->id)
									->where('schedule_units.start_time','>=',$rest_time->start_time)
									->where('schedule_units.end_time','<=',$rest_time->end_time)
									->whereNotNull('schedule_units.booking_id')
									->where('bookings.status','<>','Rest')
									->get();
									if (count($not_free_schedules)>0){
										$rest_result_message .= date('H:i',strtotime($rest_time->start_time)).'開始之休息時段與班表id'.$not_free_schedules->first()->booking_id.'之時段有衝突'."\n";
										continue;
									}
									//跟其他休息單有重複，但不管，就插單給他，然後時間格子強行覆蓋，反正現在取消的部分只會請空自己id的時間格子
									if ( $rest_time->start_time==$rest_booking->start_time ){
										$rest_cancel_result = $this->cancelSchedule($rest_booking->start_time,$group->server);
										if ( !$rest_cancel_result->result ){
											if (count($input_date_schedule_array)>1){
												return $date_value[0].'-'.$rest_cancel_result->message.'(休息';
											}
											else{
												return $rest_cancel_result->message.'(休息';
											}	
										}
									}
									$insert_array = [
										'start_time'=>$rest_time->start_time,
										'end_time'=>$rest_time->end_time,
										'server_id'=>$group->server->id,
										'sales_id'=>0,
										// 'admin_nickname'=>$GroupAdmin->nickname,
										'period'=>(strtotime($rest_time->end_time)-strtotime($rest_time->start_time))/60,
										's_time'=>'1',
										'booking_group_id'=>$group->id,
										'total_price'=> '0',
										'status'=>'Rest',
										'note'=>'',
									]; 
									$Booking = new Booking;
									$lastid = $Booking->insertGetId($insert_array);
									if ($lastid){
										$is_schedule_update_sucess = ScheduleUnit::
										where('server_id',$group->server->id)
										->where('start_time','>=', $rest_time->start_time)
										->where('end_time', '<=', $rest_time->end_time)
										->where('booking_id',NULL)
										->update(['booking_id' => $lastid]);
										if ( !$is_schedule_update_sucess ){
											if (count($input_date_schedule_array)>1){
												$result->result = false;
												$result->message = $date_value[0].'-'.'抱歉，服務員時程表更新失敗，可能發生搶訂，若有疑問請聯繫工程師';
												return $result;
											}
											else{
												$result->result = false;
												$result->message = '抱歉，服務員時程表更新失敗，可能發生搶訂，若有疑問請聯繫工程師';
												return $result;
											}
										}
									}
									else{
										if (count($input_date_schedule_array)>1){
											$result->result = false;
											$result->message = $date_value[0].'-'.'抱歉，服務訂單新增失敗，請聯繫工程師';
											return $result;
										}
										else{
											$result->result = false;
											$result->message = '抱歉，服務訂單新增失敗，請聯繫工程師';
											return $result;
										}
									}
									
								}
								// else{
								// 	$rest_result_message .= date('H:i',strtotime($rest_time->start_time)).'開始之休息時段與班表id'.$not_free_schedule->booking_id.'之時段有衝突'."\n";
								// }
							// }
						}
						else{
							$insert_array = [
								'start_time'=>$rest_time->start_time,
								'end_time'=>$rest_time->end_time,
								'server_id'=>$group->server->id,
								'sales_id'=>0,
								// 'admin_nickname'=>$GroupAdmin->nickname,
								'period'=>(strtotime($rest_time->end_time)-strtotime($rest_time->start_time))/60,
								's_time'=>'1',
								'booking_group_id'=>$group->id,
								'total_price'=> '0',
								'status'=>'Rest',
								'note'=>'',
							]; 
							$Booking = new Booking;
							$lastid = $Booking->insertGetId($insert_array);
							if ($lastid){
								$is_schedule_update_sucess = ScheduleUnit::
								where('server_id',$group->server->id)
								->where('start_time','>=', $rest_time->start_time)
								->where('end_time', '<=', $rest_time->end_time)
								->where('booking_id',NULL)
								->update(['booking_id' => $lastid]);
								if ( !$is_schedule_update_sucess ){
									if (count($input_date_schedule_array)>1){
										$result->result = false;
										$result->message = $date_value[0].'-'.'抱歉，服務員時程表更新失敗，可能發生搶訂，若有疑問請聯繫工程師';
										return $result;
									}
									else{
										$result->result = false;
										$result->message = '抱歉，服務員時程表更新失敗，可能發生搶訂，若有疑問請聯繫工程師';
										return $result;
									}
								}
							}
							else{
								if (count($input_date_schedule_array)>1){
									$result->result = false;
									$result->message = $date_value[0].'-'.'抱歉，服務訂單新增失敗，請聯繫工程師';
									return $result;
								}
								else{
									$result->result = false;
									$result->message = '抱歉，服務訂單新增失敗，請聯繫工程師';
									return $result;
								}
							}
						}

					}
					
				}

			}

			
			$daily_bookings = CommandUtil::searchDailyGroupSchedule($group->server->line_group_id);

			if ( empty($daily_bookings) ){
				$message = '目前無相關班表資訊';
				return $message;
			}
			$message = $daily_bookings;
			// print_r($message);exit;
			$return_array[] =$message;
			if ( count($canceled_array)>0 ){
				if (count($input_date_schedule_array)>1){
					$cancel_message .= '訂單取消成功:'."\n";
					foreach( $canceled_array as $value){
						
						$cancel_message .= $value."\n";
					}
				}
				else{
					$cancel_message .= '訂單取消成功:'."\n";
					foreach( $canceled_array as $value){
						$cancel_message .= $value."\n";
					}
				}

			}
			if (!empty($cancel_message)){
				if ( $index == (count($input_date_schedule_array)-1) ){
					//暫時封閉
					$return_array[] =$cancel_message;
				}
			}
			if ( isset($rest_result_message) && !empty($rest_result_message) ){
				if (count($input_date_schedule_array)>1){
					$rest_result_message = $date_value[0].'-'.$rest_result_message."\n";
				}
				else{
					$return_array[] = $rest_result_message;
				}
			}
			if (!empty($rest_result_message)){
				if ( $index == (count($input_date_schedule_array)-1) ){
					$return_array[] =$rest_result_message;
				}
			}
			if ( isset($parsing_results->alarm_msg) && !empty($parsing_results->alarm_msg) ){
				// $return_array[] = '警告:'."\n".$parsing_results->alarm_msg;
				print_r($parsing_results->alarm_msg);
			}
		return $return_array;



	}

	private function updateSchedule($user,$group_id,$booking_id,$sales_id,$service_id,$server,$input_time,$input_price,$checked_data){
		$result = (object) [];
		$result->result = true;
		$target_service = Service::where('id',$service_id)->first();
		$end_time = date('Y-m-d H:i:s',strtotime($input_time)+$target_service->period*60);
		DB::beginTransaction();
		$target_booking = Booking::where('id',$booking_id)->first();
		if ( strtotime($target_booking->end_time) > strtotime($end_time) ) {
			$pre_clear_schedule_sucess = ScheduleUnit::where('server_id',$server->id)
			->where('booking_id', $booking_id)
			->where('end_time','<=',$target_booking->end_time)
			->update(['booking_id' => NULL]);
		}

		//客出不可改
		// if ( $target_booking->status=='Close' ){
		// 	return $result;
		// }
		if ( $target_booking )
		$is_change_ready_into_pending = false;
		if ( $checked_data->booking_status_change ){
			if ( !$checked_data->get_ready && !$checked_data->get_close ){
				$is_change_ready_into_pending = true;
			}
		}
		$schedule_units = ScheduleUnit::
		where('server_id',$server->id)
		->where('start_time','>=', $input_time)
		->where('end_time', '<=', $target_booking->end_time)
		->whereNotIn('booking_id',['null',$booking_id])
		->lockForUpdate()
		->get();

		// if ( count($schedule_units->all())> 0 ){
		// 	DB::rollBack();
		// 	$result->result = false;
		// 	$result->message = '抱歉，服務員: '.$server->name.'於'.$input_time.'仍在服務中';
		// 	return $result;
		// }
		$is_schedule_update_sucess = ScheduleUnit::
		where('server_id',$server->id)
		->where('start_time','>=', $input_time)
		->where('end_time', '<=', $end_time)
		->update(['booking_id' => $booking_id]);
		if ( !$is_schedule_update_sucess ){
			$result->result = false;
			$result->message = '抱歉，服務員時程表更新失敗，可能發生搶訂，若有疑問請聯繫工程師';
			return $result;
		}

		$price=$target_service->server_fee
		+$target_service->broker_fee
		-$target_service->company_cost
		+$target_service->company_profit
		-$target_service->marketing_cost
		+$target_service->sales_profit;
		
    $special_total_price = 0;
    if ( isset($checked_data->special_service) ){

      $note = '';
      $special_service_array = $checked_data->special_service;
      $index = 1;
      foreach( $special_service_array as $special_service ){
        if ( $index >1 ){
          $note .= '+';
          if ( $special_service->input_price != 0 ){
            $note .= $special_service->input_price;
          }
          $note .= $special_service->name;
        }
        else{
          if ( $special_service->input_price != 0 ){
            $note .= $special_service->input_price;
          }
          $note .= $special_service->name;
        }
        $special_total_price  += $special_service->input_price;
        $index++;
      }
    }
		else{
			if ( isset($checked_data->bool_special_service_change) && $checked_data->bool_special_service_change ){
				if ( !empty($target_booking->note) ){
					$note = '';
				}
			}
		}

		$sales_additional_profit = $input_price - $price;


		// $GroupAdmin = GroupAdmin::where('line_user_id',$user->id)->first();

		// if ( $sales_additional_profit < 0 ){
		// 	// $result->result = false;
		// 	// $result->message = '抱歉，輸入回價低於預設值，請查明後重新輸入';
		// 	// return $result;
		// }
		$update_array =[];
		$update_array =['end_time' => $end_time,
		// 'admin_nickname'=>$GroupAdmin->nickname,
		'admin_nickname'=>$checked_data->admin_nickname,
		'sales_id'=>$sales_id,
		'period'=>$target_service->period,
		's_time'=>$target_service->s_time,
		'server_fee'=>$target_service->server_fee+$special_total_price,
		'broker_fee'=>$target_service->broker_fee,
		'company_cost'=>$target_service->company_cost,
		'company_profit'=>$target_service->company_profit,
		'marketing_cost'=>$target_service->marketing_cost,
		'sales_profit'=>$target_service->sales_profit+$sales_additional_profit,
		'total_price'=>$input_price+$special_total_price,
		];

		if ( $target_booking->is_pre_booking=='Y' ){
			if ( !isset($checked_data->is_pre_booking) ){
				$update_array['is_pre_booking'] = 'N';
			}
			if ( isset($checked_data->is_pre_booking) && $checked_data->is_pre_booking=='N' ){
				$update_array['is_pre_booking'] = 'N';
			}
		}
		else{
			if ( isset($checked_data->is_pre_booking) ){
				$update_array['is_pre_booking'] = 'Y';
			}
		}

		if ( isset($checked_data->temp_admin_remark) ){
			$update_array['remark'] = $checked_data->temp_admin_remark;
		}

		if ( $checked_data->booking_status_change ){
			//先不擋，在討論
			// $last_booking = Booking::where('start_time','<',$target_booking->start_time)
			// ->where('start_time','>',date('Y-m-d H:i:s',strtotime($target_booking->start_time)-21600))
			// ->where('status','<>','Cancel')
			// ->orderBy('start_time','desc')
			// ->first();
			// if ($last_booking){
			// 	if ( $last_booking->status=='Ready' ){
			// 		$result->result = false;
			// 		$result->message = '抱歉，'.date('Hi',strtotime($target_booking->start_time)).'前單未出，不可客進';
			// 		return $result;
			// 	}
			// }
			if ($is_change_ready_into_pending){
				$update_array['status'] = 'Pending';
			}
      if ($checked_data->get_ready){
				$update_array['status'] = 'Ready';
			}
      if ($checked_data->get_close){
        $update_array['status'] = 'Close';
        $update_array['real_end_time']=date('Y-m-d H:i:s');
			}
		}
		if ($checked_data->get_close){
			$is_schedule_update_sucess = ScheduleUnit::where('server_id',$server->id)
			->where('booking_id', $booking_id)
			->update(['booking_id' => NULL]);
		}
    if (isset($note)){
      $update_array['note'] = $note;
    }
		$is_booking_update_sucess = Booking::where('id',$booking_id)
		->update($update_array);
		
		if ( !$is_booking_update_sucess ){
			$result->result = false;
			$result->message = '抱歉，服務員訂單更新失敗，請聯繫工程師';
			return $result;
		}
		DB::commit();

		return $result;
	}

	private function insertSchedule($user,$group_id,$sales_id,$service_id,$server,$input_time,$checked_data){
		$result = (object) [];
		DB::beginTransaction();
		$result->result = true;
		$TargetService = $server->services
		->where('id',$service_id)
		->first();

		$basic_price=$TargetService->server_fee
		+$TargetService->broker_fee
		-$TargetService->company_cost
		+$TargetService->company_profit
		-$TargetService->marketing_cost
		+$TargetService->sales_profit;

    $special_total_price = 0;
 
    if ( isset($checked_data->special_service) ){
      $note = '';
      $special_service_array = $checked_data->special_service;
      $index = 1;
      foreach( $special_service_array as $special_service ){
        if ( $index >1 ){
          $note .= '+';
          if ( $special_service->input_price != 0 ){
            $note .= $special_service->input_price;
          }
          $note .= $special_service->name;
        }
        else{
          if ( $special_service->input_price != 0 ){
            $note .= $special_service->input_price;
          }
          $note .= $special_service->name;
        }
        $special_total_price  += $special_service->input_price;
        $index++;
      }
    }

		// $GroupAdmin = GroupAdmin::where('line_user_id',$user->id)->first();


		$end_time = date("Y-m-d H:i:s",strtotime($input_time)+$TargetService->period*60);
		$schedule_units = ScheduleUnit::
		where('server_id',$server->id)
			->where('start_time','>=', $input_time)
			->where('end_time', '<=', $end_time)
			// ->where('booking_id','!=','null')
			->lockForUpdate()
			->get();
			
		if ( count($schedule_units->all())== 0 ){
			DB::rollBack();
			$result->result = false;
			$result->message = '抱歉，服務員: '.$server->name.'於'.$input_time.'時段未開放';
			return $result;
		}
		// $filter_schedule_unit = $schedule_units
		// 	->where('booking_id','!=',null);
			
		// if ( count($filter_schedule_unit->all())> 0 ){
		// 	DB::rollBack();
		// 	$result->result = false;
		// 	$result->message = '抱歉，服務員: '.$server->name.'於'.$input_time.'仍在服務中';
		// 	return $result;
		// }

		$insert_array = [
			'start_time'=>$input_time,
			'end_time'=>$end_time,
			'server_id'=>$server->id,
			'sales_id'=>$sales_id,
			// 'admin_nickname'=>$GroupAdmin->nickname,
      'admin_nickname'=>$checked_data->admin_nickname,
			'period'=>$TargetService->period,
			's_time'=>$TargetService->s_time,
			'booking_group_id'=>$group_id,
			'server_fee'=>$TargetService->server_fee+$special_total_price,
			'broker_fee'=>$TargetService->broker_fee,
			'company_cost'=>$TargetService->company_cost,
			'company_profit'=>$TargetService->company_profit,
			'marketing_cost'=>$TargetService->marketing_cost,
			'sales_profit'=>$checked_data->input_price - $basic_price,
			'total_price'=> $checked_data->input_price+$special_total_price,
		];

		if ( $checked_data->booking_status_change ){
      if ($checked_data->get_ready){
				$insert_array['status'] = 'Ready';
			}
      if ($checked_data->get_close){
        $insert_array['status'] = 'Close';
        $insert_array['real_end_time']=date('Y-m-d H:i:s');
				// $is_schedule_update_sucess = ScheduleUnit::where('server_id',$server->id)
				// //原本這裡應該要給客出當下的時間，但因為反解析的暴力性，我決定只要客初就完全解放
				// ->where('start_time','>=', $input_time)
				// ->where('end_time', '<=', $end_time)
				// ->update(['booking_id' => NULL]);
			}
		}
    if (isset($note)){
      $insert_array['note'] = $note;
    }
		if ( isset($checked_data->is_pre_booking) ){
			$insert_array['is_pre_booking'] = 'Y';
		}
		if ( isset($checked_data->temp_admin_remark) ){
			$insert_array['remark'] = $checked_data->temp_admin_remark;
		}
		$Booking = new Booking;
		$lastid = $Booking->insertGetId($insert_array);
		
		if ( $lastid ){
			if (!$checked_data->get_close){
				$is_update_sucess = ScheduleUnit::
				where('server_id',$server->id)
				->where('start_time','>=', $input_time)
				->where('end_time', '<=', $end_time)
				->update(['booking_id' => $lastid]);
			}
			else{
				$is_update_sucess =true;
			}

			if ( !$is_update_sucess ){
				$result->result = false;
				$result->message = '服務員該時段被占用，可能被搶訂，請稍後重新下定其他時段';
				return $result;
			}

		}
		else{
			$result->result = false;
			$result->message = '訂單下定失敗，可能被搶訂，請稍後重新下定其他時段';
			return $result;
		}
		DB::commit();
		return $result;
	}

	private function cancelSchedule( $cancel_time,$server ){
		$result = (object) [];
		$result->result = true;
		
		$target_booking = Booking::where('start_time',$cancel_time)
		->where('server_id',$server->id)->first();
    if ( $target_booking->status == 'Close' )
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
		// $is_update_schedule_sucess = ScheduleUnit::
		// where('server_id',$server->id)
		// ->where('start_time','>=', $cancel_time)
		// ->where('end_time', '<=', $target_booking->end_time)
		// ->where('booking_id',$target_booking->id)
		// ->update(['booking_id' => NULL]);
		// if ( !$is_update_schedule_sucess	){

		// 	$result->result = false;
		// 	$result->message = '訂單取消後釋放服務員行程失敗，請聯繫系統工程師';
		// 	return $result;
		// }
		$result->cancel_time = date("m/d H:i",strtotime($cancel_time));
		if ( date("H",strtotime($cancel_time)) == '00' ){
			$result->cancel_time = date("m/d",strtotime($cancel_time)-86400).' 24:'.date("i",strtotime($cancel_time));
		}

		if ( !empty($target_booking->admin_nickname) ){
			$result->cancel_time.=$target_booking->admin_nickname;
		}
		else{
			$result->cancel_time.='~'. date("m/d H:i",strtotime($target_booking->end_time)).'休息';
			return $result;
		}
		$result->cancel_time.=$target_booking->s_time.'/'.$target_booking->period.'/'.$target_booking->total_price;
		$if_admin_exist = GroupAdmin::where('nickname',$target_booking->admin_nickname)->first();
		if ($if_admin_exist){
			
			$partner_sales_auth = PartnerSalesAuth::where('partner_id',$if_admin_exist->partner_id)->where('sales_id',$target_booking->sales_id)->first();
			if ($partner_sales_auth){
				$result->cancel_time.='('.$partner_sales_auth->sales_group_code;
			}
		}
		return $result;
	}

	private function getScheduleDate( &$command ){
		$month = substr($command,0,2);
		$day = substr($command,3,2);
		$year = date('Y');
		if ( date('H')<config('app.system.day_split_hour') ){
			$year = date('Y',strtotime('-1 day'));
		}
		$date = $year.'-'.$month.'-'.$day;
		return $date;
	}

	private function checkTommorowScheduleExist( $command ){
		$command = trim($command,"\n");
		$tmp = [];
		$tmp = explode("\n",$command);
		$date_index = ['0'];
		$date_schedule_array = [];
		$temp = [];
		foreach ( $tmp as  $value ){
			if (!empty($value)){
				$temp[] = $value;
			}	
		}

		foreach ( $temp as $index => $value ){
			if ($index==0){
				continue;
			}
			$value = trim($value);
			if ( is_numeric(substr($value,0,2)) && is_numeric(substr($value,3,2)) && (substr($value,2,1)=='/')){
				$date_pos = array_search($value,$tmp);
				if (isset($tmp[$date_pos-1]) && !empty($tmp[$date_pos-1])){
					print_r('反解析，日期上面沒空行');
					exit;
				}
				$date_index[]=$index;
			}
			
		}

		if (count($date_index)==1){
			return [$temp];
		}

		$schedule_temp = [];
		$break_line_array = [];

		foreach ( $temp as $index => $value ){
			if( !isset($temp[$index+1]) || (in_array($index+1,$date_index)) ){
				if ( !in_array($value,$break_line_array) ){
					$break_line_array[]=$value;
				}
				if ( count($break_line_array)>0){
					$schedule_temp[] = $break_line_array;
					$break_line_array = [];
				}
			}
			else{
				$break_line_array[]=$value;
			}

		}
		return $schedule_temp;
	}

	//這裡只驗證資料完整性，但不保證資料正確性，後續仍須要去查方案是否存在，加值方案有沒有問題等等
	private function scheduleParsing($command,$server,$partner_id_array){
		$result = (object) [];
		$command = trim($command,"\n");
		$msg_array = explode("\n",$command);
		// print_r($msg_array);exit;
		$is_work_end = false;
		$result->is_legal = true;
		$year = date('Y');
		
		//跨年單問題,如果在01/01,但時間還在工作換日時間內,則年份還在去年
		if ( date('H')<config('app.system.day_split_hour') )
			$year = date('Y',strtotime('-1 day'));

		//如果是班表資訊一定有超過一行;第一行要是日期mm/dd
		if ( preg_match('/^[0-9]{,2}\/[0-9]{,2}/',$msg_array[0],$date) ){
			$schedule_date = $date[0];
			list($month, $day) = explode('/',$schedule_date);
			if ( checkdate($month,$day,$year) ){
				$result->is_legal = false;
				$result->message = '錯誤，班表日期不合法';
				return $result;
			}
		}

		$server=Server::where('id',$server->id)->first();

		// 檢查班表是否只有輸入日期,或是日期跟下班字串,若是空班表,刪除當日班內所有未完成的訂單
		if ( (count($msg_array)==2 && $msg_array[1]=='########') || (count($msg_array)==1) ){

			$daily_schedule = [];

			$date = $this->getScheduleDate($msg_array[0]);
			
			$split_hour = config('app.system.day_split_hour');
			$split_hour_string = str_pad($split_hour,2,'0',STR_PAD_LEFT);
			$search_datetime = date("Y-m-d {$split_hour_string}:00:00",strtotime($date));
	
			$end_search_datetime = date("Y-m-d {$split_hour_string}:00:00", strtotime($date)+86400);
		
			
			$daily_schedule = explode("\n",CommandUtil::searchDailyGroupSchedule($server->line_group_id,$search_datetime,$end_search_datetime));

			// 目前班表是空的,不需要做任何異動
			if ( empty($daily_schedule) ){
				$result->is_legal = false;
				$result->message = config('app.debug') ? '目前無相關班表資訊' : false;
				return $result;
			}
	
			$month = substr($daily_schedule[0],0,2);
			$day = substr($daily_schedule[0],3,2);
			$canceled_array =[];

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
				$booking_in_db = Booking::where('start_time',$start_time)
					->where('server_id',$server->id)
					->first();
				if ( $booking_in_db ){
					if ( $booking_in_db->status =='Close'){
						continue;
					}
					if ( $booking_in_db->is_pre_booking =='Y'){
						continue;
					}
					$result = $this->cancelSchedule($start_time,$server);
					if ( !$result->result ){
						return $result;
					}
					$canceled_array[] = $result->cancel_time;
				}
			}

			$result->is_legal = false;
			$result->message = $canceled_array ? "訂單取消成功:\n" : '無取消的訂單';
			$result->after_cancel=true;
			$result->canceled_array = $canceled_array;
			foreach( $canceled_array as $value)
				$result->message .= $value."\n";
			
			return $result;					
		}

		$schedule_array = [];
		$return_msg = '';
		$return_alarm_msg = '';
		foreach ( $msg_array as $key => $value ){			
			if ( $key < 1 ) continue; // 第0項是日期,跳過

			$checked_data = (object) [];
			$checked_data->get_ready = false;
      $checked_data->get_close = false;
			$tmp_msg = '';
			$alarm_msg = '';
			// 使用者輸入的班表結尾是'**'表示客已出
			if ( substr($value,-1) == '*' ){
				$value = substr($value,0,-1);
				$checked_data->get_close = true;
			}
      else if( substr($value,-1) == '^' ){
				$value = substr($value,0,-1);
				$checked_data->get_ready = true;
      }
			// if ( strpos($value,'(')!=false ){
			// 	if (strlen(strstr($value,'('))==6){
			// 		if ( substr($value,-1) == 'e' ){
			// 			$value = substr($value,0,-1);
			// 			$checked_data->is_pre_booking = 'Y';
			// 		}
			// 	}
			// }
			// else{
			// 	if ( substr($value,-1) == 'e' ){
			// 		$value = substr($value,0,-1);
			// 		$checked_data->is_pre_booking = 'Y';
			// 	}
			// }
			if ( substr($value,-1) == 'e' ){
				$value = substr($value,0,-1);
				$checked_data->is_pre_booking = 'Y';
			}
			$time = mb_substr( $value,0,5 );
			$month = substr($msg_array[0],0,2);
			$day = substr($msg_array[0],3,2);
			$hour = substr($time,0,2);
			$minutes = substr($time,3,2);
			$admin_nickname = mb_substr( $value,5,1 );
			if ($admin_nickname == '~'){
				$bool_rest = strpos($value,'休息');
				if ($bool_rest==false){
					$bool_rest = strpos($value,'吃飯');
					if ($bool_rest == false){
						// '，目前服務員休息的範例為=> 18:00~21:00吃飯 or 18:00~21:00休息、'
						$tmp_msg .= '未註明休息或吃飯、';
					}
					else{
						$bool_rest = true;
					}
				}
				else{
					$bool_rest = true;
				}
				if ($bool_rest){
					$value = str_replace(' ','',$value);
					//進這邊表示 裡面有休息跟吃飯的字串，接著要驗證是否有血時間區間
					$time2 = mb_substr( $value,6,5 );
					$hour2 = substr($time2,0,2);
					$minutes2 = substr($time2,3,2);
					if ($hour < 0 || $hour > 24 || !is_numeric($hour) || $minutes < 0 || $minutes > 59 || !is_numeric($minutes) ){
						$tmp_msg .=  '休息開始時段之時間不合法、';
					}
					if ($hour2 < 0 || $hour2 > 24 || !is_numeric($hour2) || $minutes2 < 0 || $minutes2 > 59 || !is_numeric($minutes2) ){
						$tmp_msg .=  '休息結束時段之時間不合法、';
					}

					$curr_timestamp = strtotime($year.'-'.$month.'-'.$day.' '.'00:00:00');

					if ( $hour < config('app.system.day_split_hour') ){
						$year = date('Y',$curr_timestamp+86400);
						$month = date('m',$curr_timestamp+86400);
						$day = date('d',$curr_timestamp+86400);
					}
					else if($hour==24){
						$year = date('Y',$curr_timestamp+86400);
						$month = date('m',$curr_timestamp+86400);
						$day = date('d',$curr_timestamp+86400);
						$hour = '00';
					}
					$year2 = $year;
					$month2 = $month;
					$day2 = $day;
					if ( $hour2 < config('app.system.day_split_hour') ){
						// 使用者發文日期與班表日期相等,
						$year2 = date('Y',$curr_timestamp+86400);
						$month2 = date('m',$curr_timestamp+86400);
						$day2 = date('d',$curr_timestamp+86400);
					}
					else if($hour2==24){
						$year2 = date('Y',$curr_timestamp+86400);
						$month2 = date('m',$curr_timestamp+86400);
						$day2 = date('d',$curr_timestamp+86400);
						$hour2 = '00';
					}
					$rest_start_time = $this->timeFormat($year.'-'.$month.'-'.$day.' '.$hour.':'.$minutes.':00');
					$rest_end_time = $this->timeFormat($year2.'-'.$month2.'-'.$day2.' '.$hour2.':'.$minutes2.':00');

					if (strtotime($rest_start_time)>strtotime($rest_end_time)){
						$tmp_msg .= '休息時段之起始時間晚於開始時間、';
					}
					if ( !empty($tmp_msg) ){
						$return_msg .=  "● 第{$key}條班表({$time})訊息之".trim($tmp_msg,"、")."\n";
						$result->is_legal = false;
						$result->message = $return_msg;
						return $result;
					}
					if (Session::has('rest_time_array')){

					}
					$rest_time_array = (object)[];
					$rest_time_array->start_time = $rest_start_time;
					$rest_time_array->end_time = $rest_end_time;
					Session::push('rest_time_array', $rest_time_array);
					continue;
				}
			}
			$value = mb_substr( $value,6,mb_strlen($value)-6 );
			$temp = explode('(',$value);
			$service = $temp[0];
			$sales_group_code = null;
			$sales_group_partner_id = $server->partner_id;
			//上面就拆解完了，接下來各種驗
			if ($hour < 0 || $hour > 24 || !is_numeric($hour) || $minutes < 0 || $minutes > 59 || !is_numeric($minutes) ) 
				$tmp_msg .= '時間不合法、';
			if ( !preg_match('/[a-zA-Z]*\p{Han}*[a-zA-Z]*/u',$admin_nickname) )
				$tmp_msg .= '廠商代號須為中或英文、';
			else{

				if ( $admin_nickname!='智' ){
					$if_admin_nickname_exist = GroupAdmin::where('nickname',$admin_nickname)->first();

					if (!$if_admin_nickname_exist){
	
						$if_temp_admin_nickname_exist = temp_group_admin::where('nickname',$admin_nickname)->first();
						if (!$if_temp_admin_nickname_exist){
							$tmp_msg .= '商代號不存在、';
						}
						else{

							$sales = Sales::where('line_user_id',$if_temp_admin_nickname_exist->line_user_id)->first();
							if (!$sales){
								$tmp_msg .= '找不到配對的業務';
							}
							$tmp =	PartnerSalesAuth::where('sales_id',$sales->id)->where('partner_id',$if_temp_admin_nickname_exist->partner_id)->first();
							if (!$tmp){
								$tmp_msg .= '找不到配對的業務代碼';
							}
							else{
								if ( isset($temp[1]) && !empty($temp[1]) ){
									$checked_data->temp_admin_remark = $temp[1];
								}
								$temp[1]=$tmp->sales_group_code;
								$sales_group_partner_id = $if_temp_admin_nickname_exist->partner_id;
							}

						}
					}
				}
			}
			
			if ( !isset($temp[1]) ){
				$tmp_msg .= '班表信息未包含業務代碼、';
			}

			else{				
				$sales_group_code = substr($temp[1],0,4);

				$partner_sales_data = PartnerSalesAuth::where('partner_id',$sales_group_partner_id)->where('sales_group_code',$sales_group_code)->first();
				if ( !$partner_sales_data ){
					$tmp_msg .= '業務代碼有誤、';
				}
			}
			unset($temp);
			$temp = explode('+',$service);
			$service = $temp[0];
			//這裡的方案詳細資訊是來自使用者輸入的班表 => 30/1/1800 => 服務時間/次數/總收金額

			$temp_service = [];
			$temp_service = explode('/',$service);
			$s_time = $temp_service[0];
			$period = $temp_service[1];
			$price = $temp_service[2];

			$curr_timestamp = strtotime($year.'-'.$month.'-'.$day.' '.'00:00:00');

			if ( $hour < config('app.system.day_split_hour') ){
				$year = date('Y',$curr_timestamp+86400);
				$month = date('m',$curr_timestamp+86400);
				$day = date('d',$curr_timestamp+86400);
			}
			else if($hour==24){
				$year = date('Y',$curr_timestamp+86400);
				$month = date('m',$curr_timestamp+86400);
				$day = date('d',$curr_timestamp+86400);
				$hour = '00';
			}

			$curr_timestamp = time();

			$start_time = $this->timeFormat($year.'-'.$month.'-'.$day.' '.$hour.':'.$minutes.':00');

			$booking_info = Booking::where('start_time',$start_time)
				->where('server_id',$server->id)
				->where('status','!=','Cancel')
				->first();
			
			if ( $booking_info ){
				$checked_data->sales_id = $booking_info->sales_id;
				
				$booking_period = $booking_info->period;
				$booking_s_time = $booking_info->s_time;
				$booking_price = $booking_info->total_price;
				//未來廠商部分，要注意更換了廠商會有硬上問題 

				//這裡的方案詳細資訊是來自使用者的訂單
				if ( !preg_match('/^[0-9]{2}/',$period) || !preg_match('/^[0-9]{1}/',$s_time) || !preg_match('/^[0-9]{4}/',$price) )
					$tmp_msg .= '方案內容與格式不正確、';

				$server_admin_exist = GroupAdmin::where('nickname',$booking_info->admin_nickname)->first();
				if ($server_admin_exist){
					$booking_sales_group_code = PartnerSalesAuth::where('sales_id',$booking_info->sales_id)->where('partner_id',$server_admin_exist->partner_id)->first()->sales_group_code;
				}
				else{
					$server_temp_admin_nickname_exist = temp_group_admin::where('nickname',$booking_info->admin_nickname)->first();
					if ($server_temp_admin_nickname_exist){
						$booking_sales_group_code = PartnerSalesAuth::where('sales_id',$booking_info->sales_id)->where('partner_id',$server_temp_admin_nickname_exist->partner_id)->first()->sales_group_code;
					}
				}
				if (!isset($booking_sales_group_code)){
					$tmp_msg .= '該訂單管理員不存在，請聯繫工程師';
					$result->is_legal = false;
					$result->message = "● 第{$key}條班表({$time})訊息之".trim($tmp_msg,"、");
					return $result;
				}

				$bool_booking_status_change = false;

				if ( $checked_data->get_ready ){
					if ( ($booking_info->status!='Close') && ($booking_info->status!='Ready') ){
						$bool_booking_status_change = true;
					}
				}
        else if ( $checked_data->get_close ){
					if ( ($booking_info->status!='Close') ){
						$bool_booking_status_change = true;
					}
				}
				else{
					if ( ($booking_info->status=='Ready') ){
						$bool_booking_status_change = true;
					}
					//客出不給改
					if ( ($booking_info->status=='Close') ){
						$bool_booking_status_change = true;
					}
				}
        $bool_special_service_change = false;

				$bool_remark_change = false;
				if (isset($checked_data->temp_admin_remark)){
					if ($checked_data->temp_admin_remark != $booking_info->remark){
						$bool_remark_change = true;
					}
				}
				else{
					if ( !empty($booking_info->remark) ){
						$bool_remark_change = true;
						$checked_data->temp_admin_remark = '';
					}
				}
				$bool_pre_booking_change = false;
				if (isset($checked_data->is_pre_booking)){
					if ($checked_data->is_pre_booking != $booking_info->is_pre_booking){
						$bool_pre_booking_change = true;
						$checked_data->is_pre_booking = 'Y';
					}
				}
				else{
					if ( $booking_info->is_pre_booking == 'Y' ){
						$checked_data->is_pre_booking = 'N';
						$bool_pre_booking_change = true;
					}
				}
        if (isset($temp[1])){
          $tmp = [];
          $tmp = explode('=',$temp[1]);
          $input_special_arr = [];
          $input_special_arr = explode('+',$tmp[0]);
          $db_special_arr = [];
          $db_special_arr = explode('+',$booking_info->note);
          $tmp = [];
          $tmp = array_diff($input_special_arr,$db_special_arr);
          if (count($tmp)>0){
            $bool_special_service_change = true;
          }
         
          unset($tmp);
          unset($input_special_arr);
          unset($db_special_arr);
        }
				else{

					$db_special_arr = [];
          $db_special_arr = explode('+',$booking_info->note);
					if ( !empty($db_special_arr[0]) ){
						$bool_special_service_change = true;
					}
				}
				
				// 服務時間、次數、價格、下訂人、業務員、訂單狀態都有改變才處理
				if ( $period != $booking_period || $s_time != $booking_s_time || $price != $booking_price || $booking_info->admin_nickname != $admin_nickname || $booking_sales_group_code != $sales_group_code || $bool_booking_status_change || $bool_special_service_change || $bool_remark_change || $bool_pre_booking_change){
					$target_service = Service::where('server_id',$server->id)
					->where('period',$period)
					->where('s_time',$s_time)
					->first();
					if ( !$target_service ){
						$tmp_msg .= '服務員並沒有提供此方案、';
						$result->is_legal = false;
						$result->message = "● 第{$key}條班表({$time})訊息之".trim($tmp_msg,"、");
						return $result;
					}
					else{
            $target_price=$target_service->server_fee
            +$target_service->broker_fee
            -$target_service->company_cost
            +$target_service->company_profit
            -$target_service->marketing_cost
            +$target_service->sales_profit;
          }
					// if ( isset($if_temp_admin_nickname_exist) && $if_temp_admin_nickname_exist){
					// 	if ( $price < $target_price ){
					// 		$alarm_msg .= "方案價格比原方案基礎回價低，基礎回價為{$target_price}、";
					// 	}
					// }
					if ( $price < $target_price ){
						$alarm_msg .= "方案價格比原方案基礎回價低，基礎回價為{$target_price}、";
					}
					$end_time = date('Y-m-d H:i:s',strtotime($start_time)+$period*60);

					$schedule_units = ScheduleUnit::
						where('server_id',$server->id)
						->where('start_time','>=', $start_time)
						->where('end_time', '<=', $end_time)
						->get();
					if ( count($schedule_units->all())== 0 )
						$tmp_msg .= "服務員於{$start_time}~{$end_time}間時段未開放，無法下定{$period}分之方案、";

					// $filter_schedule_unit =	ScheduleUnit::
					// 	where('server_id',$server->id)
					// 	->where('start_time','>=', $start_time)
					// 	->where('end_time', '<=', $end_time)
					// 	->where('booking_id','!=','null')
          //   ->get()
          //   ->groupBy('booking_id');

					// if ( count($filter_schedule_unit)> 1 ){
					// 	$tmp_msg .= "服務員於{$start_time}~{$end_time}間已有其他訂單，無法更新{$period}分之方案、";
					// }
					if ($booking_info->admin_nickname != $admin_nickname && $admin_nickname=='智'){
						$tmp_msg .= "原本代理人為{$booking_info->admin_nickname}，不得改為智、";
					}
					//後面版本這邊要驗證，理論上只能改管理只能改自己
					$checked_data->admin_nickname = $admin_nickname;
					if ( $booking_sales_group_code != $sales_group_code )
            if ( isset($partner_sales_data->sales_id) )
						  $checked_data->sales_id = $partner_sales_data->sales_id;
					$checked_data->bool_special_service_change=$bool_special_service_change;
					$checked_data->booking_status_change = $bool_booking_status_change;
					$checked_data->input_price = $price;
					$checked_data->booking_id =$booking_info->id;
					$checked_data->start_timestamp = strtotime($start_time);
					if (isset($target_service)){
						$checked_data->end_timestamp = strtotime($start_time)+$target_service->period*60;
						$checked_data->target_service_id =$target_service->id;
					}
					$checked_data->input_time = $start_time;
					$checked_data->status = 'update';
				}
				// 服務時間、次數、價格、下訂人、業務員、訂單狀態都有改變才處理都相同
				else{
					if ( $booking_info->total_price != $price )
						$tmp_msg .= '方案價格與原本訂單總價不同、';
          else{
            $checked_data->booking_id =$booking_info->id;
            $checked_data->input_time = $start_time;
						$checked_data->start_timestamp = strtotime($start_time);
						$checked_data->end_timestamp = strtotime($start_time)+$booking_info->period*60;
            $checked_data->status = 'nochange';
          }
				}
			}
			//這裡表示輸入之時段並沒有相關訂單，所以實際上是新增
			else{
				$target_service = Service::where('server_id',$server->id)
					->where('period',$period)
					->where('s_time',$s_time)
					->first();
				if ( !$target_service ){
					$tmp_msg .= '服務員並沒有提供此方案、';
					$result->is_legal = false;
					$result->message = "● 第{$key}條班表({$time})訊息之".trim($tmp_msg,"、");
					return $result;
				}
        else{
          $target_price=$target_service->server_fee
          +$target_service->broker_fee
          -$target_service->company_cost
          +$target_service->company_profit
          -$target_service->marketing_cost
          +$target_service->sales_profit;
	
          if ( $price < $target_price ){
						$alarm_msg .= "方案價格大於輸入方案之總價,方案價格為{$target_price}、";
					}
          $checked_data->booking_status_change = ( $checked_data->get_close || $checked_data->get_ready);
          $checked_data->admin_nickname = $admin_nickname;
          $checked_data->input_price = $price;
					$checked_data->start_timestamp = strtotime($start_time);
					if (isset($target_service)){
						$checked_data->end_timestamp = strtotime($start_time)+$target_service->period*60;
						$checked_data->target_service_id =$target_service->id;
					}
          $checked_data->input_time = $start_time;
          if ( isset( $partner_sales_data->sales_id) ){
            $checked_data->sales_id = $partner_sales_data->sales_id;
          }
          $checked_data->status = 'insert';
        }
			}

			//這個紀錄這一筆訂單所含之加值服務
			$special_service_array = [];
			$msg_special = '';
			for ( $i=1;$i<count($temp);$i++ ){

        if ( end($temp)==$temp[$i] ){
          $tmp =[];
          $tmp=explode('=',$temp[$i]);
          $temp[$i]=$tmp[0];
        }
				$special_service = trim($temp[$i]);
				// 方案格式:{數字}{方案名(可能有空格)},ex:"2000no condom","500口"
				if( preg_match('/^[0-9]+.+/u',$special_service,$tmp) ){
					preg_match('/[0-9]+/',$special_service,$tmp);
					$special_service_price = $tmp[0];
					$special_service_name = str_replace($special_service_price,'',$special_service);
					$target_service = Service::where('server_id',$server->id)
						->where('name',$special_service_name)
						->first();
					if ( !$target_service ){
						$msg_special .= "{$special_service_name},";
						continue;
					}
          $target_service->input_price = $special_service_price;
					$special_service_array[] = $target_service;
				}
				else{
          if(empty($special_service)){
            $msg_special .= '未指定服務,';
            continue;
          }

					$target_service = Service::where('server_id',$server->id)
          ->where('name',$special_service)
          ->first();

					if (!$target_service){
						$msg_special .= "{$special_service},";
						continue;
          }
          $target_service->input_price = $target_service->server_fee;
					$special_service_array[] = $target_service;
				}
			}// end for
			$msg_special = trim($msg_special,',');
			if( $msg_special )
				$tmp_msg .= "特殊服務無({$msg_special})之服務、";

			if ( !$result->is_legal ){
				return $result;
			}
			if ( count($special_service_array)>0 ){
				$checked_data->special_service = $special_service_array;
			}
			$schedule_array[] = $checked_data;

      if ( !empty($tmp_msg) ){
				$return_msg .=  "● 第{$key}條班表({$time})訊息之".trim($tmp_msg,"、")."\n";
      }
			if ( !empty($alarm_msg) ){
				$return_alarm_msg .=  "● 第{$key}條班表({$time})訊息之".trim($alarm_msg,"、")."\n";
      }
		}// end foreach 解析使用者輸入的班表

		//這裡抓出session想辦法繼續
		$sessions = Session::get('rest_time_array');
		Session::forget('rest_time_array');
		if (!empty($sessions)){
			$rest_schedule_array = [];



			foreach ( $sessions as $index => $session ){
				$rest_time_data = (object)[];
				$rest_time_data->is_rest_time_illegal = false;
				$rest_time_data->input_time = $session->start_time;
				$rest_time_data->start_time = $session->start_time;
				$rest_time_data->start_timestamp  = strtotime($session->start_time);
				$rest_time_data->end_time = $session->end_time;
				$rest_time_data->end_timestamp = strtotime($session->end_time);
				$rest_time_data->status = 'rest';
				$rest_time_data->result = true;
				
				foreach ( $schedule_array as $schedule ){
					if ( ( (strtotime($session->start_time) >= $schedule->start_timestamp) && (strtotime($session->start_time) < $schedule->end_timestamp) )  || ( (strtotime($session->end_time) > $schedule->start_timestamp) && (strtotime($session->end_time) <= $schedule->end_timestamp)) ){
						//進來這邊表示有占用到班表內其他時間
						if ( !$schedule->get_close ){
							$rest_time_data->is_rest_time_illegal = true;
							break;
						}
					}
				}
				if ($rest_time_data->is_rest_time_illegal){
					break;
				}
				$rest_schedule_array[] = $rest_time_data;

			}
			if ( $rest_time_data->is_rest_time_illegal ){
				$return_msg =  date('H:i',strtotime($rest_time_data->start_time)).'~'.date('H:i',strtotime($rest_time_data->end_time)).'休息時段占用到輸入之班表時段';
			}
			else{
				$result->rest_time_array = $sessions;
			}
			if ( count($rest_schedule_array) > 0 ){
				foreach ( $rest_schedule_array as $rest_schedule ){
					$schedule_array[] = $rest_schedule;
				}
			}

		}


		$return_msg = trim($return_msg,"\n");

		$return_alarm_msg = trim($return_alarm_msg,"\n");
		$result->is_legal = strlen($return_msg)==0;
		$result->message = $return_msg;
    $result->alarm_msg = $return_alarm_msg;
		$result->schedule_parsing_result = $schedule_array;


		return $result;
	}

	// 將時間對應到schedule unit的切割時間中
	private function timeFormat($time){
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
				$hour = str_pad($hour,2,'0',STR_PAD_LEFT);
				$minute = '00';
			}
			else{
				$minute = $next_unit_minute;
			}
		}
		$minute = str_pad($minute,2,'0',STR_PAD_LEFT);
		return $date.' '.$hour.':'.$minute.':00';
	}
	private function deleteAllSchedule($server,$daily_bookings,$schedule){
		$result = (object)[];
		$result->result = false;
		$result->message = '';

		foreach ( $daily_bookings as $daily_booking ){
			//現階段先真的刪除
			$target_booking = Booking::
			where('id',$daily_booking->id)->where('is_pre_booking','N')->first();
			if ( $target_booking ){
				$is_update_booking_sucess = $target_booking->delete();
				if ( !$is_update_booking_sucess	){
					$result->result = false;
					$result->message = '訂單刪除失敗id:'.$daily_booking->id.'，請聯繫系統工程師';
					return $result;
				}
			}
		}
		$result->result = true;
		return $result;
	}

	protected function SessionFunction( $args=null ) : string {
		
	}
}
