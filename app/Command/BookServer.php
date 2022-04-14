<?php
namespace App\Command;

use DB;
use App\Model\Server;
use App\Model\Booking;
use App\Model\Service;
use App\Model\GroupAdmin;
use App\Model\Sales;
use App\Model\ScheduleUnit;
use App\Model\PartnerSalesAuth;
use App\Line\ApiHelper;
class BookServer extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new BookServer();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '預約',
			'cmd' => '約',
			'description' => '搜尋指定人的當前狀態',
			'args' => [
				'時間','人名','方案'
			],
			'access' => ['admin','group_admin','sales'],
			'authorized_group_type' => ['Booking'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."{時間}{人名}{方案},例如:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."現在花花60/2/2500\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."0130小春40/N/6000+無套";

		$user = $args->user;
		$group = $args->group;
		if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return $message;
		}
		$command = $args->command;
		//測試用test
		// $command = '約s110)2135花花20/3/2500+冰火+900乳 交';
		//承上，既然知道該管理員握有那些partner_id，等等就看業務 及服務員是不是也有對應的partner_id
		//* 仍有問題待解決，代訂先不搞 */
		$info = $this->getBookingInfo( $command );

		if ( !empty($info['Message']) ){
			 return $info['Message'];
		}
		if( !$info['Result'] ){
			$message = str_replace('E00','E01',$message);
			return $message;
		}

		$info = $info['data'];
		$sales = $user->sales;
		if ( $info['sales'] )
			$sales = $info['sales'];
		else if (!$sales){
			return '您不具有業務身分，請聯繫系統管理員';
		}

		if( $group->partners->count()>1 )
			return "本群綁定超過2個廠商，不接受下訂";

		$partner = $group->partners->first();
		if( !$partner )
			return "本群未綁定任何廠商，無法下定";

		$admin_access = false;
		foreach( $user->group_admins as $admin ){
			if( $admin->partner->id==$partner->id ){
				$admin_access = true;
				break;
			}
		}
		if( !$admin_access && $info['is_admin_command'] )
			return "非合作廠商指定管理員，無法代訂";
		
		//針對時間下定這個問題，不是說不可以定已經過的時間，而是要詢問說下定時間是不是跨日下定
		if (	isset($info['day'])	){
			$search_datetime = $info['day'].' '.substr($info['time'],0,-2).':'.substr($info['time'],2).':00';
		}
		else{
			$search_datetime = date('Y-m-d').' '.substr($info['time'],0,-2).':'.substr($info['time'],2).':00';
			if (	substr($info['time'],0,-2) < 8 && substr($info['time'],0,-2) > 0 && date('H') > 8	){
				$search_datetime = date('Y-m-d',strtotime('+1 day')).' '.substr($info['time'],0,-2).':'.substr($info['time'],2).':00';
			}
		}

		$search_datetime = $this->timeformate($search_datetime);
		//因為timeformate出來的 是以五分鐘單位的整點 如: 11:55:30 會被整理成 11:55:00
		//因此這邊nowtime -300 s 這樣就穩了
		if ( (strtotime("now")-300) > strtotime($search_datetime) ){
			if (substr($info['time'],0,-2)>8){
				return '下訂失敗，欲下訂時間已過';
			}
			if (!isset($info['day'])){
				return '下訂失敗，欲下訂時間已過';
			}
			$search_datetime = date('Y-m-d',strtotime('+1 day')).' '.substr($info['time'],0,-2).':'.substr($info['time'],2).':00';
			$search_datetime = $this->timeformate($search_datetime);
		}
		if (	(strtotime($search_datetime)-strtotime("now")) > 604800){
			return '下訂失敗，無法預訂超過七天後的時段';
		}

		// 列出所有在本群所屬合作廠商的指定服務員
		$servers = Server::where('name','like',"%".$info['name']."%")->where('partner_id',$partner->id)->where('enable','<>','N')->get();
		if( $servers->count()>1 ){
			$msg = "搜尋到多位服務員，請明確指定要預訂哪位：\n";
			foreach( $servers as $server ){
				$msg .= $server->name."\n";
			}
			$msg = trim($msg,"\n");
			return $msg;
		}
		$server = $servers->first();
		
		if( $server ){
		
			$specialService = array();
			$specialService = explode('+',$info['case']);
			$service_info = array();
			//這裡的specialService基本上就是 60/1/3000之類，但有可能變成只有60，或者60/2，或者60/2/6000之類，若沒有s_time那就默認為1
			$service_info =  explode("/",$specialService[0]);
			
			if ( empty($service_info[0]) ){
				return '未輸入方案內容下定不成立';
		   		}
			if ( !preg_match('/^[0-9]{2}/',$service_info[0]) ){
				if ( $service_info[0]==' 長' || $service_info[0]==' 短' ){
					$tmp=explode(' ',$service_info[0]);
					$service_info[0]=$tmp[1];
				}
				if(  $service_info[0]=='長' || $service_info[0]=='短' ){
					if ( isset($service_info[1]) ){
						if ( preg_match('/^[0-9]{1}/',$service_info[1]) || $service_info[1]=='n' || $service_info[1]=='ns' ){
							if ( !isset($service_info[2]) && $service_info[1]>10 ){
								$service_info[2]=$service_info[1];
								$service_info[1]=1;
							}
							$TargetService = $server->services->where('description',$service_info[0].'時服務')
							->where('s_time',substr($service_info[1],0,1))
							->first();
							if ( !$TargetService ){
								//有空時把這裡reply該服務員有的方案
								return '抱歉，該服務員無'.$service_info[0].'/'.$service_info[1].'之方案';
							}
							$period=$TargetService->period;
							$shot=$TargetService->s_time;
							$price=$TargetService->server_fee
							+$TargetService->broker_fee
							-$TargetService->company_cost
							+$TargetService->company_profit
							-$TargetService->marketing_cost
							+$TargetService->sales_profit;
							if ( isset($service_info[2]) ){
								if ($service_info[2]>=$price){
									$price = $service_info[2];
								}
								else{
									return '錯誤:指定金額小於原方案金額';
								}
								if ( isset($specialService[1]) && is_numeric($specialService[1]) ){
									return '格式錯誤，不可同時指定方案價又加註業務分潤';
								}
							}
							
						}					
						else{
							return '格式錯誤:方案中間請輸入單一數字或n';
						}
					}
					else{
						$TargetService = $server->services->where('description',$service_info[0].'時服務')
						->where('s_time',1)->first();
						if ( !$TargetService ){
							//有空時把這裡reply該服務員有的方案
							return '抱歉，該服務員並無'. $service_info[0].'時方案';
						}
						$period=$TargetService->period;
						$shot=$TargetService->s_time;
						$price=$TargetService->server_fee
						+$TargetService->broker_fee
						-$TargetService->company_cost
						+$TargetService->company_profit
						-$TargetService->marketing_cost
						+$TargetService->sales_profit;
					}

				}
				else{
					return '格式錯誤:方案開頭請輸入服務時長，如:60,30等，或者  "長","短"';
				}
			}
			else{
				if ( !empty($service_info[1]) ){
					if ( preg_match('/^[0-9]{1}/',$service_info[1]) || $service_info[1]=='n' || $service_info[1]=='ns' ){
						if ( !isset($service_info[2]) && $service_info[1]>10 ){
							$service_info[2]=$service_info[1];
							$service_info[1]=1;
						}
						$TargetService = $server->services->where('period',substr($service_info[0],0,2))
						->where('s_time',substr($service_info[1],0,1))->first();
	
						if ( !$TargetService ){
							$Services = Service::where('server_id',$server->id)->where('name','like','%service')->get();

							$msg='';
			
							foreach( $Services as $service ){
								$msg .= $service->period.'/'.$service->s_time.'s'."\n";
							}
							if ( !empty($msg) ){
									return '該服務員沒有'.substr($service_info[0],0,2).'分的'.substr($service_info[1],0,1).'s方案'."\n".'該服務員有的方案為:'."\n".$msg;
							}
							else{
									return '該服務員未設定任何方案';
							}
						}
						$period=$TargetService->period;
						$shot=$TargetService->s_time;
						$price=$TargetService->server_fee
						+$TargetService->broker_fee
						-$TargetService->company_cost
						+$TargetService->company_profit
						-$TargetService->marketing_cost
						+$TargetService->sales_profit;
						if ( isset($service_info[2]) ){
							if ($service_info[2]>=$price){
								$price = $service_info[2];
							}
							else{
								return '錯誤:指定金額小於原方案金額';
							}
							if (isset($specialService[1]) && is_numeric($specialService[1])){
								return '格式錯誤，不可同時指定方案價又加註業務分潤';
							}
						}
						

					}
					else{
						return '格式錯誤:方案中間請輸入單一數字或n';
					}
				}
				else{
					//這裡有時間沒次數，次數預設為1，並且時間是數字
					$TargetService = $server->services->where('period',substr($service_info[0],0,2))
					->where('s_time',1)->first();
					if ( !$TargetService ){
						$Services = $server->services->where('period',substr($service_info[0],0,2))
						->pluck('s_time');
						$msg='';
						foreach( $Services as $service ){
							$msg .= $service[0].'s,';
						}
						if ( !empty($msg) ){
								return '該服務員沒有'.substr($service_info[0],0,2).'的1s方案'."\n".'該服務員有的'.substr($service_info[0],0,2).'分方案為:'.$msg;
						}
						else{
								return '該服務員沒有'.substr($service_info[0],0,2).'的相關方案';
						}
					}
					$period=$TargetService->period;
					$shot=$TargetService->s_time;
					$price=$TargetService->server_fee
					+$TargetService->broker_fee
					-$TargetService->company_cost
					+$TargetService->company_profit
					-$TargetService->marketing_cost
					+$TargetService->sales_profit;

				}

			}
			$special_service_total_price = 0;
			//$specialService[1]表示業務分潤，如果有標註，那必須扣回欲設方案的業務分潤
			if ( !empty($specialService[1]) ){
				 if (	isset($service_info[2]) && is_numeric($specialService[1]) ){
					 return '格式錯誤，不可同時指定方案價又加註業務分潤';
				 }
				 $i=1;
				 if ( is_numeric($specialService[1]) ){
					$price =  $price + ( $specialService[1]*100 ) - $TargetService->sales_profit;
					$i=2;
				 }

				 $note ='';
				 //過濾加值服務的數字，有數字就認可，沒數字就查service
				 for ( ;!empty($specialService[$i]); $i++ ){
					$filter_num = filter_var($specialService[$i], FILTER_SANITIZE_NUMBER_INT);
					if ( $filter_num ){
						$special_service_total_price += $filter_num;
					}
					else{
						$SingleSpecialService = $server->services->where('name',$specialService[$i])->first();
						if ( $SingleSpecialService ){
							 $special_service_total_price += $SingleSpecialService->server_fee;
						}
						else{
							return '服務項目名稱錯誤，或者未提供此服務項目';
						}
					}
					$note .= $specialService[$i];
					if ( (count($specialService)-1) != $i ){
						$note.='+';
					}
				 }
				 $price += $special_service_total_price;
		   }

			$available = CommandUtil::getSingalFreeServerOnSpecificTime($server, $search_datetime);
			if( !$available->available ){
				$message = "抱歉，服務員: {$info['name']}時段未開放或該時段已在服務中";
				return $message;
			}

			$end_time = date("Y-m-d H:i:s",strtotime($search_datetime.' +'.$period.'minute'));
			if ( in_array(trim($period),$available->periods) ){
				if( $TargetService->salesCost()>$price )
					return "價格小於業務成本，無法下訂";

				//未來 米要改成廠商縮寫(估計可能要新增一個欄位)
				if ( $info['is_admin_command'] ){
					$GroupAdmin = GroupAdmin::where('line_user_id',$user->id)->first();
					$admin_nickname = $GroupAdmin->nickname;
				}
				else{
					$admin_nickname = '智';
				}

				// try{
					DB::beginTransaction();
					$schedule_units = $server->schedule_units()
						->where('start_time','>=', $search_datetime)
						->where('end_time', '<=', $end_time)
						// ->where('booking_id','!=','null')
						->lockForUpdate()
						->get();

					if ( count($schedule_units->all())== 0 ){
						DB::rollBack();
						return '抱歉，服務員: '.$info['name'].'於'.$info['time'].'時段未開放';
					}
					$filter_schedule_unit = $schedule_units
						->where('booking_id','!=',null);
						
					if ( count($filter_schedule_unit->all())> 0 ){
						DB::rollBack();
						return '抱歉，服務員: '.$info['name'].'於'.$info['time'].'仍在服務中';
					}
					$sales_profit = $TargetService->sales_profit;
					if ( isset($specialService[1]) && is_numeric($specialService[1]) ){
						$sales_profit = $TargetService->sales_profit + ( $specialService[1]*100 );
					}
					else{
						if ( isset($service_info[2]) ){
							$sales_profit = $service_info[2]-$TargetService->salesCost();
						}
					}
					
					$insert_array = [
						'start_time'=>$search_datetime,
						'end_time'=>$end_time,
						'server_id'=>$server->id,
						'sales_id'=>$sales->id,
						'admin_nickname'=>$admin_nickname,
						'period'=>$TargetService->period,
						's_time'=>$TargetService->s_time,
						'booking_group_id'=>$group->id,
						'server_fee'=>$TargetService->server_fee+$special_service_total_price,
						'broker_fee'=>$TargetService->broker_fee,
						'company_cost'=>$TargetService->company_cost,
						'company_profit'=>$TargetService->company_profit,
						'marketing_cost'=>$TargetService->marketing_cost,
						'sales_profit'=>$sales_profit,
						'total_price'=> $price,
					];
					if (isset($note)){
						$insert_array['note'] = $note;
					}
					$Booking = new Booking;
					$lastid = $Booking->insertGetId($insert_array);

					if ( $lastid ){

						$is_update_sucess = $server->schedule_units()
						->where('server_id',$server->id)
						->where('start_time','>=', $search_datetime)
						->where('end_time', '<=', $end_time)
						->update(['booking_id' => $lastid]);
	
						if ( !$is_update_sucess ){
							return '服務員該時段被占用，可能被搶訂，請稍後重新下定其他時段';
						}

					}
					else{
						return '訂單下定失敗，可能被搶訂，請稍後重新下定其他時段';
					}
					DB::commit();

				// }catch (\Exception $exception){
				// 	return '下訂失敗';
				// }
				$DailySchedule = CommandUtil::searchDailyGroupSchedule($server->line_group_id);
				if ( empty($DailySchedule) ){
					$DailySchedule = '目前無相關班表資訊';
				}
				$helper = ApiHelper::helper(env('LINE_BOT_CHANNEL_ACCESS_TOKEN'));
				$messages = [
					[	'type' => 'text',	'text' => $DailySchedule ],
				];
				$result = $helper->push($server->line_group_id, $messages, true);
				if ( $info['sales'] ){
						return '代訂-業務'.$info['sales_group_code'].'預約成功'.$command.'('.$lastid;
				}
				return '預約成功'.$command.'('.$lastid;
			}
			else{
				return "抱歉，服務員: {$info['name']}沒有{$period}的服務時段方案";
				//這裡記得要回傳有的方案
			}
		}
		else{
			$message = "查無相關名稱的服務人員";
			return $message;
		}


		

	}

	private function getBookingInfo( &$command ){
		if ( $command=='約花花假資料' ){
			 $i=1;
			 for (;$i<7;$i++){
				$this->Schedule_unit_seeds(date('Y-m-d H:i:s'),$i);
			 }
			 return ['Result'=>true,'Message'=>'資料插入完成'];
		}

		$name = null;
		$CaseArr = array();
		$tmp = array();

		if( strpos($command,$this->command_data['cmd'])===0 ){
			
			if ( mb_strlen($command)==1 || ( (mb_strlen($command)==2) && mb_substr($command,1)=='?' ) ){
				$message = "*下單必須在群組中下單，訂單才會成立*\n".
					"下單格式範例\n".
					$this->command_data['pre_command'].$this->command_data['cmd']."{時間}{人名}{方案},例如:\n".
					$this->command_data['pre_command'].$this->command_data['cmd']."現在花花60/2/2500\n".
					$this->command_data['pre_command'].$this->command_data['cmd']."0130小春40/N/6000+無套";
				return ['Result'=>true,'Message'=>$message];				 		
			}
			$name = $this->command_data['cmd'];
			//去頭之後的內容
			$command = substr($command,strlen($name));
			$ExplodeArray  = explode(')',$command);
			//如果[1]有炸出東西，表示)存在，那就是代訂
			//這個東西下一版做，除了groupadmin代訂，還要考慮admin代訂，資料格式可能會是p{廠商id}{業務id}後面再接指令
			$sales = null;
			$is_admin_command = false;
			if ( !empty($ExplodeArray[1]) ){
				$is_admin_command = true;
				$partner_sales_auth = PartnerSalesAuth::where('sales_group_code',$ExplodeArray[0])->first();
				if( !$partner_sales_auth )
					return ['Result'=>false,'Message'=>'搜尋不到指定業務代碼'];
				$sales = Sales::where('id',$partner_sales_auth->sales_id)->first();
				if( !$sales )
					return ['Result'=>false,'Message'=>'搜尋不到指定業務'];
				$command = $ExplodeArray[1];
				$CaseArr['sales_group_code'] = $ExplodeArray[0];
			}
			//這樣寫很醜，非常醜，但管他的改天再改
			if ( preg_match('/^\p{Han}+[0-9]{8}/u',$command,$tmp) ){
				$command = substr($command,strlen($tmp[0]));
				$command=mb_substr($tmp[0],-8).mb_substr($tmp[0],0,-8).$command;
			}
			else if ( preg_match('/^\p{Han}+[0-9]{4}/u',$command,$tmp) ){
				$command = substr($command,strlen($tmp[0]));
				$command=mb_substr($tmp[0],-4).mb_substr($tmp[0],0,-4).$command;
			}
			else if ( mb_strpos($command,'現在',0,"utf-8") ){
				$CommandName = mb_substr($command,0,mb_strpos($command,'現在',0,"utf-8"));
				$command = mb_substr($command,mb_strpos($command,'現在',0,"utf-8")+2);
				$command = '現在'.$CommandName.$command;
			}
			else if ( mb_strpos($command,'目前',0,"utf-8") ){
				$CommandName = mb_substr($command,0,mb_strpos($command,'目前',0,"utf-8"));
				$command = mb_substr($command,mb_strpos($command,'目前',0,"utf-8")+2);
				$command = '目前'.$CommandName.$command;
			}
			else if (  mb_strpos($command,'now',0,"utf-8") ){
				$CommandName = mb_substr($command,0,mb_strpos($command,'now',0,"utf-8"));
				$command = mb_substr($command,mb_strpos($command,'now',0,"utf-8")+2);
				$command = 'now'.$CommandName.$command;
			}

			if ( preg_match('/^[0-9]{8}/',$command,$tmp) ){
				// 包含了日期
				$bool_date_legal = checkdate(substr($tmp[0],0,2),substr($tmp[0],2,2),date('Y'));
				if ( !$bool_date_legal ){
					return ['Result'=>false,'Message'=>'輸入之日期為非法'];
				}
				$CaseArr['day'] = date('Y').'-'.substr($tmp[0],0,2).'-'.substr($tmp[0],2,2);
				if (	date('m')==12 && substr($tmp[0],0,2)=='01' ){
					$CaseArr['day'] = date('Y', strtotime('+1 years')).'-'.substr($tmp[0],0,2).'-'.substr($tmp[0],2,2);
				}
				$CaseArr['time'] = substr($tmp[0],4,4);
			}
			else if( preg_match('/^[0-9]{4}/',$command,$tmp) ){
				// 搜尋到的時間在指令最前頭
					$CaseArr['time'] = $tmp[0];
			}
			else if( strpos($command,'現在')===0 ){
				$CaseArr['time'] = date("Hi");
				$command=str_replace('現在',$CaseArr['time'],$command);
			}
			else if( strpos($command,'目前')===0 ){
				$CaseArr['time'] = date("Hi");
				$command=str_replace('目前',$CaseArr['time'],$command);
			}
			else if( strpos($command,'now')===0 ){
				$CaseArr['time'] = date("Hi");
				$command=str_replace('now',$CaseArr['time'],$command);
			}
			else{
				//說明開頭不是時間，看看開頭是不是人名
				return ['Result'=>false];
			}
			
			
			$command = substr($command,strlen($CaseArr['time']));
			//如果day存在，表示有給日期加幾點幾分，那這裡就要多拿掉四格
			if ( isset($CaseArr['day']) ){
				$command = substr($command,0,4);
			}
			//若為24xx 則當作是隔天的00:00，配合資料庫
			if(	substr(	$CaseArr['time'],0,2	) == 24 ){
				$CaseArr['time']='00'.substr($tmp[0],2,2);
				if ( isset($CaseArr['day']) ){
					$CaseArr['day'] = date('Y-m-d',strtotime($CaseArr['day'])+86400);
				}
				else{
					$CaseArr['day'] = date('Y-m-d', strtotime('+1 day'));
				}
			}
			// 搜尋漢字
			if( preg_match('/^[a-zA-Z]*\p{Han}*[\x{3105}-\x{3129}]*[a-zA-Z]*/u',$command,$tmp) ){
				$CaseArr['name'] = $tmp[0];
			}
			$command = substr($command,strlen($CaseArr['name']));
			$CaseArr['case'] = $command;
			$CaseArr['sales']= $sales;
			$CaseArr['is_admin_command']= $is_admin_command;
			if (	empty($CaseArr['name']) || empty($CaseArr['case']) || empty($CaseArr['time']) ){
				return ['Result'=>false,'Message'=>'輸入資料有誤，請聯繫系統服務商'];
			}
			return ['Result'=>true,'data'=>$CaseArr];
		}
		else{
			return ['Result'=>false];
		}		

	}

	protected function SessionFunction( $args=null ) : string {
		
	}

	//插假資料用的，目前都直接插7天，未來應該每天差第八天，確保永遠有這麼多天
	private function Schedule_unit_seeds($BeginTime,$server_id=1){
		$BeginTime=$this->timeformate($BeginTime);
		$BeginTime=strtotime($BeginTime);
		$EndTime=$BeginTime+604800;

		$server = Server::find($server_id);
		$server->start_time = date('Y-m-d H:i:s',$BeginTime);
		$server->end_time = date('Y-m-d H:i:s',$EndTime);
		$server->save();

		while($EndTime>$BeginTime){
			DB::table('schedule_units')->insert([
				[
					'created_at'=>date('Y-m-d H:i:s'),
					'start_time'=>date('Y-m-d H:i:s',$BeginTime),
					'end_time'=>date('Y-m-d H:i:s',$BeginTime+300),
					'server_id'=>$server_id,
				],
			]);
			$BeginTime=$BeginTime+300;
		}
	
	}

	private function timeformate($time){

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


}
