<?php
namespace App\Command;

use App\Model\Server;
use App\Model\Service;
use App\Model\Broker;
use App\Model\Partner;
use App\Model\Country;
use App\Model\Area;
use App\Model\ServiceList;
use App\Model\ServerCreateData;
use App\Model\ScheduleUnit;
use App\Model\PartnerGroupPair;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class SetServer extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new SetServer();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '&',
			'session_functions' => [
				'setName',
				'setImg',
				'setPartner',
				'setServices'
			],
			'name' => '設定服務員',
			'cmd' => '設服務',
			'description' => '查看群組內的成員名單',
			'args' => [],
			'access' => ['admin','group_admin'],
			'reply_questions' => ['請輸入服務員名稱','請上傳服務員照片','請輸入服務員綁定之廠商id(目前建議都先綁2)','請幫服務員設定方案'],
			'authorized_group_type' => ['Admin','Server'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
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
		$partner_id_array = [];
		foreach( $user->group_admins as $group_admin){
			array_push($partner_id_array,$group_admin->partner_id);
		}

		$partner_group_pairs = PartnerGroupPair::where('line_group_id',$group->id)->get();
		$group_type_array = [];
		foreach( $partner_group_pairs as $partner_group_pair ){
			array_push($group_type_array,$partner_group_pair->group_type);
		}

		//過濾指令字
		$command_msg = substr($command, strlen($this->command_data['cmd']));
		$break_line_list = [];
		$break_line_list = explode("\n",$command_msg);

		if ( empty($break_line_list[0]) ){
			return '請在設服務後接上服務員名稱';
		}
		$tmp = [];
		$name = '';
		$other_command = strstr($break_line_list[0],'-');

		if ( !empty($other_command) ){
			$break_line_list[0] = substr($break_line_list[0],0,-(strlen($other_command)) );
		}
		if( preg_match('/[a-zA-Z]*\p{Han}*[\x{3105}-\x{3129}]*[a-zA-Z]*/u',$break_line_list[0],$tmp) && !empty($tmp[0]) ){
			// 搜尋到的漢字在指令最前頭
			if( strpos($command_msg,$tmp[0])===0 ){
				$name = $tmp[0];
			}
			else{
				return '服務員名稱必須為中文或英文';
			}
		}
		else{
			return '服務員名稱須為中文或英文';
		}
	
		//可以再減號後面帶 特殊 或者 方案
		if ( $other_command == '-方案' ){
			if ( !in_array('Admin',$group_type_array) ){
				return '此群組不是管理群，請再次確認';
			}
			$result = $this->checkService($break_line_list,$partner_id_array);
			if (!$result->result){
				return $result->message;
			}
			$target_server  = Server::where('name',$name)->whereIn('partner_id',$partner_id_array)->first();
			if ($target_server){
				return '服務員'.$name.'已存在，不可重複設定';
			}
			$update_result = ServerCreateData::updateOrCreate(
				['name'=>$name,'partner_id'=>$result->input_partner_id],
				['services'=>json_encode($result->checked_service_array)]
			);
			if ($update_result){
				$message = '已新增至暫存表，當前方案為:'."\n".'(分鐘/次/妹拿/經濟拿/店利)'."\n".'&設服務'.$update_result->name.'-方案'."\n".'partner_id:'.$update_result->partner_id."\n";
				$services = json_decode($update_result->services,true);
				foreach ( $services as $service ){
					$message .= $service."\n";
				}
				return $message;
			}
		}
		if ( $other_command == '-特殊' ){
			if ( !in_array('Admin',$group_type_array) ){
				return '此群組不是管理群，請再次確認';
			}
			$result = $this->checkSpecialService($break_line_list,$partner_id_array);
			if (!$result->result){
				return $result->message;
			}
			$target_server  = Server::where('name',$name)->whereIn('partner_id',$partner_id_array)->first();
			if ($target_server){
				return '服務員'.$name.'已存在，不可重複設定';
			}
			$update_result = ServerCreateData::updateOrCreate(
				['name'=>$name,'partner_id'=>$result->input_partner_id],
				['special_service'=>json_encode($result->ckecked_array)]
			);
			if ($update_result){
				$message = '已新增至暫存表，當前特殊服務方案為:'."\n".'&設服務'.$update_result->name.'-特殊'."\n".'partner_id:'.$update_result->partner_id."\n";
				$special_services = json_decode($update_result->special_service,true);

				foreach ( $special_services as $special_service ){
					$message .= $special_service."\n";
				}
				return $message;
			}
		}
		if ( $other_command == '-標記' ){
			if ( !in_array('Server',$group_type_array) ){
				return '此群組不是服務群，請再次確認';
			}
			if ( !strstr($break_line_list[1],'partner_id:') ){
				return '第二行格式為 partner_id:{您的廠商id}';
			}
			$input_partner_id = substr($break_line_list[1],-(strlen($break_line_list[1])-11));
			if ( !in_array($input_partner_id,$partner_id_array) ){
				return '您不具有廠商'.$input_partner_id.'的身分';
			}
			if (!isset($args->mention)){
				return '請tag妹妹line帳號';
			}
			$mentions = $args->mention;
			if ( count($mentions->mentionees) != 1 ){
				return 'tag超過一個帳號，請再次確認';
			}
			if (!isset($mentions->mentionees[0]->userId)){
				return 'tag的帳號沒有設定個人line id，無法設為服務員line帳號';
			}
			$if_temp_exist = ServerCreateData::where('name',$name)->where('partner_id',$input_partner_id)->first();
			if ( $if_temp_exist ){
				$if_group_used = Server::where('line_group_id',$group->id)->first();
				if ( $if_group_used ){
					return '此群組已為服務員'.$if_group_used->name.'綁定之群組，請聯繫工程師修改';
				}
				$result = ServerCreateData::updateOrCreate(
					['name'=>$name,'partner_id'=>$input_partner_id],
					['line_user_id'=>$mentions->mentionees[0]->userId,'line_group_id'=>$group->id]
				);
				if ( !empty($result->line_user_id) && !empty($result->line_group_id)){
					return $name.'綁line帳號及群組成功'."\n".'&設服務-標記'."\n".'partner_id:x(廠商代號)'."\n".'@人名';
				}
				else{
					return '更新失敗';
				}
			}
			else{
				$if_server_exist = Server::where('name',$name)->where('partner_id',$input_partner_id)->first();
				if (!$if_server_exist){
					return '沒找到標記的服務員';
				}
				$update_user_group_id = [];
				$update_user_group_id['line_user_id'] = $mentions->mentionees[0]->userId;
				$update_user_group_id['line_group_id'] = $group->id;
				if ( !empty($if_server_exist->duty_start_time) && !empty($if_server_exist->duty_end_time)){
					$start_time = date('Y-m-d ').$if_server_exist->duty_start_time;
					$end_time = date('Y-m-d ').$if_server_exist->duty_end_time;
					if (substr($if_server_exist->duty_start_time,0,2)<config('app.system.day_split_hour')){
						$start_time = date('Y-m-d ',strtotime("+1 day")).$if_server_exist->duty_start_time;
					}
					if (substr($if_server_exist->duty_end_time,0,2)<config('app.system.day_split_hour')){
						$end_time = date('Y-m-d ',strtotime("+1 day")).$if_server_exist->duty_end_time;
					}
					$schedule_begin_time=strtotime(date('Y-m-d 07:00:00'));
					$schedule_end_time=$schedule_begin_time+86400*2-300;
					while($schedule_end_time>$schedule_begin_time){
						$result = ScheduleUnit::updateOrCreate(
							['start_time'=>date('Y-m-d H:i:s',$schedule_begin_time),'server_id'=>$if_server_exist->id],
							['end_time'=>date('Y-m-d H:i:s',$schedule_begin_time+300)]
						);
						$schedule_begin_time += 300;
					}
					$update_user_group_id['start_time'] = $start_time;
					$update_user_group_id['end_time'] = $end_time;
				}
				$result = Server::updateOrCreate(
					['name'=>$name,'partner_id'=>$input_partner_id],
					$update_user_group_id
				);
				if ( !empty($result->line_user_id) && !empty($result->line_group_id)){
					if ( isset($update_user_group_id['start_time']) ){
						return $name.'綁line帳號及群組成功'.'且已設於'.$update_user_group_id['start_time'].'上班';
					}
					return $name.'綁line帳號及群組成功';
				}
				else{
					return '更新失敗';
				}

			}



		}
		if ( $other_command == '-完成' ){
			if ( !in_array('Admin',$group_type_array) ){
				return '此群組不是管理群，請再次確認';
			}
			if ( !strstr($break_line_list[1],'partner_id:') ){
				return '第二行格式為 partner_id:{您的廠商id}';
			}
			$input_partner_id = substr($break_line_list[1],-(strlen($break_line_list[1])-11));
			if ( !in_array($input_partner_id,$partner_id_array) ){
				return '您不具有廠商'.$input_partner_id.'的身分';
			}
			$target_server  = Server::where('name',$name)->whereIn('partner_id',$partner_id_array)->first();
			if ($target_server){
				return '服務員'.$name.'已存在，不可重複設定';
			}
			$server_create_data = ServerCreateData::where('name',$name)->where('partner_id',$input_partner_id)->first();
			$msg = $name;
			if ( !$server_create_data ){
				return '您目前未暫存服務員:'.$name.',partner_id:'.$input_partner_id;
			}
			if ( empty($server_create_data->line_user_id) ){
				$msg .= '-帳號(X)';
			}
			else{
				$msg .= '-帳號(V)';
			}
			if ( empty($server_create_data->broker_id) || empty($server_create_data->country_id) || empty($server_create_data->lanague) || empty($server_create_data->service_type) || empty($server_create_data->service_type) || empty($server_create_data->height) || empty($server_create_data->weight) || empty($server_create_data->cup) || empty($server_create_data->age) || empty($server_create_data->description) ){
				$msg .= '-資料(X)';
			}
			else{
				$msg .= '-資料(V)';
			}
			if ( empty($server_create_data->services) ){
				$msg .= '-方案(X)';
			}
			else{
				$msg .= '-方案(V)';
			}
			if ( empty($server_create_data->special_service ) ){
				$msg .= '-服務(X)';
			}
			else{
				$msg .= '-服務(V)';
			}
			// if ( empty($server_create_data->duty_start_time ) ){
			// 	$msg .= '-上班(X)';
			// }
			// else{
			// 	$msg .= '-上班(V)';
			// }
			// if ( empty($server_create_data->duty_end_time ) ){
			// 	$msg .= '-下班(X)';
			// }
			// else{
			// 	$msg .= '-下班(V)';
			// }
			if ( empty($server_create_data->special_service ) || empty($server_create_data->services) ){
				return '服務及方案為必填，請補齊資訊在嘗試'."\n".'當前->'.$msg;
			}
			else{

				$server_columns = ['line_user_id','line_group_id','broker_id','country_id','lanague','service_type','height','weight','cup','age','description'];
				$server_basic_datas = [];
				foreach ( $server_columns as $server_column ){
					if ( !empty($server_create_data->$server_column) ){
						$server_basic_datas[$server_column] = $server_create_data->$server_column;
					}
				}
				//先插入服務員資料
				$basic_data_update_result = Server::updateOrCreate(
					['name'=>$server_create_data->name,'partner_id'=>$server_create_data->partner_id],
					$server_basic_datas
				);
				if (!$basic_data_update_result){
					return '匯入服務員基礎資料發生錯誤，請通知系統工程師';
				}
				else{
					//插入服務員方案
					$server_service_datas = [];
					$server_service_datas = json_decode($server_create_data->services,true);
					foreach ( $server_service_datas as $server_service_data ){
						$temp = [];
						$temp = explode('/',$server_service_data);
						if (!isset($temp[4])){
							return '錯誤1，匯入到一半失敗，但服務員已新增，可能會發生無可用方案，請盡速通知系統管理員，以避免下定發生嚴重錯誤';
						}
						$service_input = [];
						if ($temp[0]>30){
							$service_input['name'] = 'long_service';
							$service_input['description'] = '長時服務';
						}
						else{
							$service_input['name'] = 'short_service';
							$service_input['description'] = '短時服務';
						}

						$service_input['s_time'] = $temp[1];
						$service_input['server_fee'] = $temp[2];
						$service_input['broker_fee'] = $temp[3];
						$service_input['company_profit'] = $temp[4]-$temp[2]-$temp[3];

						$service_data_update_result = Service::updateOrCreate(
							['server_id'=>$basic_data_update_result->id,'period'=>$temp[0],'s_time'=>$temp[1]],
							$service_input
						);
						if (!$service_data_update_result){
							return '錯誤2，匯入到一半失敗，但服務員已新增，可能會發生無可用方案，請盡速通知系統管理員，以避免下定發生嚴重錯誤';
						}
					}
					//插入服務員特殊方案
					$server_special_service_datas = [];
					$server_special_service_datas = json_decode($server_create_data->special_service,true);
					foreach ($server_special_service_datas as $server_special_service_data){
						$temp = [];
						$temp = explode(':',$server_special_service_data);
						$special_service_input = [];
						$special_service_input['description'] = '特殊服務';
						if (is_numeric($temp[1])){
							$special_service_input['server_fee'] = $temp[1];
						}
						$special_service_data_update_result = Service::updateOrCreate(
							['server_id'=>$basic_data_update_result->id,'name'=>$temp[0]],
							$special_service_input
						);
						if (!$special_service_data_update_result){
							return '錯誤，特殊方案匯入到一半失敗，但服務員及方案已新增，請盡速通知系統管理員，以避免下定發生嚴重錯誤';
						}
					}
					
					$server_create_data->delete();
					return '恭喜，服務員'.$server_create_data->name.'廠商id:'.$server_create_data->partner_id.'資料及方案皆已設定完成';

				}

			}
		}
		else if(!empty($other_command)){
			return '-號後請加上 方案或特殊';
		}

		$target_server  = Server::where('name',$name)->whereIn('partner_id',$partner_id_array)->first();
		if ($target_server){
			return '服務員'.$name.'已存在，不可重複設定';
		}
		$check_data = $this->checkData($break_line_list,$partner_id_array);
		if ( isset($check_data->result) ){
			return $check_data->message;
		}
		$check_data->name = $name;
		$check_data = (array)$check_data;
		$check_data['description'] = json_encode($check_data['description']);

		$result = ServerCreateData::updateOrCreate(
			['name'=>$name,'partner_id'=>$check_data['partner_id']],
			$check_data
		);

		if ( $result ){
			$message = '已新增至暫存表，當前為:'."\n".'&設服務'.$result->name."\n";
			if ( !empty($result->partner_id) ){
				$message .= 'partner_id:'.$result->partner_id."\n";
			}
			if ( !empty($result->broker_id) ){
				$message .= 'broker_id:'.$result->broker_id."\n";
			}
			if ( !empty($result->duty_start_time) ){
				$start_hour = substr($result->duty_start_time,0,2);
				$start_minute  = substr($result->duty_start_time,3,2);
				$message .= '上班時間:'.$start_hour.$start_minute."\n";
			}
			if ( !empty($result->duty_end_time) ){
				$end_hour = substr($result->duty_end_time,0,2);
				$end_minute  = substr($result->duty_end_time,3,2);
				$message .= '下班時間:'.$end_hour.$end_minute."\n";
			}
			// if ( !empty($result->area_id) ){
			// 	$area=Area::where('id',$result->area_id)->first();
			// 	if ($area){
			// 		$message .= '據點:'.$area->name."\n";
			// 	}
			// }
			if ( !empty($result->country_id) ){
				$country=Country::where('id',$result->country_id)->first();
				if ($country){
					$message .= '國籍:'.$country->name."\n";
				}
			}
			if ( !empty($result->lanague) ){
				$lanague = str_replace(',','/',$result->lanague);
				$message .= '語言:'.$lanague."\n";
			}
			if ( !empty($result->height) && !empty($result->weight) && !empty($result->cup) && !empty($result->age) ){
				$body_info = $result->height.'.'.$result->weight.'.'.$result->cup.'.'.$result->age;
				$message .= '身體資訊:'.$body_info."\n";
			}
			if ( !empty($result->service_type) ){
				$service_type = str_replace(',','/',$result->service_type);
				$message .= '服務類型:'.$service_type."\n";
			}
			if ( !empty($result->description) ){
				
				$description = json_decode($result->description,true);
				$special_tags = $description['special_tags'];
				$msg = '';
				foreach ( $special_tags as $special_tag ){
					if ( $special_tag == end($special_tags) ){
						$msg .= $special_tag;
					}
					else{
						$msg .= $special_tag.'.';
					}
				}
				$message .= '特色標籤:'.$msg."\n";
			}

			return $message;
		}

		// $msg_list = [];
		// if ( !empty($command_msg) ){
		// 	if ( $this->setName($command_msg) ){
		// 		 $msg_list = [$this->setName($command_msg)];
		// 	}
			
		// }
		// Redis::del(md5($user->id.$group->id));
		// $session_exist  = Redis::hmget(md5($user->id.$group->id),'timestamp');

		// if ( !$session_exist[0] ){
		// 	 Redis::hmset(md5($user->id.$group->id),'timestamp',strtotime('now'),'classname',__CLASS__,'msg_list',json_encode($msg_list));
		// }
		
		// $message = $this->command_data['reply_questions'][0];
		$message = trim($message,"\n");

		return $message;
	}


	protected function SessionFunction( $args=null ) : string {
		
	}
	private function setData($break_line_list,$partner_id_array){
		$result  = (object)[];
		$result->result = true;
		if ( !strstr($break_line_list[1],'partner_id:') ){
			$result->result = false;
			$result->message = '第二行格式為 partner_id:{您的廠商id}';
			return $result;
		}
		$result->input_partner_id = substr($break_line_list[1],-(strlen($break_line_list[1])-11));
		if ( !in_array($result->input_partner_id,$partner_id_array) ){
			$result->result = false;
			$result->message = '您不具有廠商'.$result->input_partner_id.'的身分';
			return $result;
		}
		$server_create_data = ServerCreateData::where('name',$name)->where('partnr_id',$result->input_partner_id)->first();
		if (!$server_create_data){
			$result->result = false;
			$result->message = '服務員'.$name.',廠商id:'.$result->input_partner_id.'未暫存';
			return $result;
		}
		$target_server = Server::where('name',$name)->where('partnr_id',$result->input_partner_id)->first();
		if (!$target_server){
			$result->result = false;
			$result->message = '服務員'.$name.',廠商id:'.$result->input_partner_id.'已存在於服務員列表，無法設定';
			return $result;
		}
		if ( empty($server_create_data->services) ){
			$result->result = false;
			$result->message = '服務員'.$name.',廠商id:'.$result->input_partner_id.'無暫存任何方案，無法繼續設定';
			return $result;
		}
		//未完待續
	}
	private function checkData($break_line_list,$partner_id_array){

		// $server_data_items = ['partner_id','broker_id','上班時間','下班時間','據點','國籍','語言','身體資訊','服務類型','特色標籤'];
		$server_data_items = ['partner_id','broker_id','上班時間','下班時間','國籍','語言','身體資訊','服務類型','特色標籤'];
		$server_data  = (object)[];
		$server_data->description = (object)[];
		for ( $i=1;$i<count($break_line_list);$i++ ){
			$break_line_list[$i] = str_replace(" ",'',$break_line_list[$i]);
			$temp_array = [];
			$temp_array = explode(':',$break_line_list[$i]);
			if ( !empty($temp_array[0]) ){
				if ( !isset($temp_array[1]) ){
					$server_data->result = false;
					$server_data->message = $temp_array.'項目沒輸入資料';
					return $server_data;
				}
			}
			if ( in_array($temp_array[0],$server_data_items) ){
				if ( $temp_array[0] == 'partner_id' ){
					if ( !is_numeric($temp_array[1]) ){
						$server_data->result = false;
						$server_data->message = '廠商id須為數字';
						return $server_data;
					}
					$result = Partner::where('id',$temp_array[1])->get();
					if (!$result){
						$server_data->result = false;
						$server_data->message = '未找到對應廠商';
						return $server_data;
					}
					if ( !in_array($temp_array[1],$partner_id_array) ){
						$server_data->result = false;
						$server_data->message = '您不具有廠商'.$temp_array[1].'的身分';
						return $server_data;
					}
				}
				if ( $temp_array[0] == 'broker_id' ){
					if ( !is_numeric($temp_array[1]) ){
						$server_data->result = false;
						$server_data->message = '經紀人id須為數字';
						return $server_data;
					}
					$result = Broker::where('id',$temp_array[1])->first();
					if (!$result){
						$server_data->result = false;
						$server_data->message = '未找到對應廠商';
						return $server_data;
					}
				}
				if ( $temp_array[0] == '上班時間' ){
					$start_hour = substr($temp_array[1],0,2);
					$start_minute  = substr($temp_array[1],2,2);
					if ($start_hour < 0 || $start_hour > 24 || !is_numeric($start_hour) || $start_minute < 0 || $start_minute > 59 || !is_numeric($start_minute) ){
						$server_data->result = false;
						$server_data->message = '上班時間，格式範例=> 0930';
						return $server_data;
					}
					$temp_array[0] = 'duty_start_time';
					$temp_array[1] = $start_hour.':'.$start_minute;
				}
				if ( $temp_array[0] == '下班時間' ){
					$end_hour = substr($temp_array[1],0,2);
					$end_minute  = substr($temp_array[1],2,2);
					if ($start_hour < 0 || $start_hour > 24 || !is_numeric($start_hour) || $start_minute < 0 || $start_minute > 59 || !is_numeric($start_minute) ){
						$server_data->result = false;
						$server_data->message = '下班時間，格式範例=> 0230';
						$temp_array[1] = $end_hour.':'.$end_minute;
						return $server_data;
					}
					$temp_array[0] = 'duty_end_time';
				}

				// if ( $temp_array[0] == '據點' ){
					
				// 	$result = Area::where('name',$temp_array[1])->first();
				// 	if (!$result){
				// 		$server_data->result = false;
				// 		$server_data->message = '未找到對應據點';
				// 		return $server_data;
				// 	}
				// 	$temp_array[0]='area_id';
				// 	$temp_array[1]= $result->id;
				// }
				if ( $temp_array[0] == '國籍' ){
					$result = Country::where('name',$temp_array[1])->first();
					if (!$result){
						$server_data->result = false;
						$server_data->message = '未找到對應國籍';
						return $server_data;
					}
					$temp_array[0]='country_id';
					$temp_array[1]= $result->id;
				}
				if ( $temp_array[0] == '語言' ){
					$lanagues = ['英文','中文','泰文'];
					$temps =[];
					$temps = explode('/',$temp_array[1]);
					foreach($temps as $temp){
						if ( !in_array($temp,$lanagues) ){
							$server_data->result = false;
							$server_data->message = $temp.'並非預設語言之一';
							return $server_data;
						}
					}
					$temp_array[0]='lanague';
					$temp_array[1]=str_replace("/",",",$temp_array[1]);
				}
				if ( $temp_array[0] == '身體資訊' ){
					$temps =[];
					$temps = explode('.',$temp_array[1]);
					if (!isset($temps[3])){
						$server_data->result = false;
						$server_data->message = '身體資訊須完整輸入(高.重.奶.歲)，如:160.80.D.21';
						return $server_data;
					}
					if ( !is_numeric($temps[0]) || !is_numeric($temps[1]) || !is_numeric($temps[3]) || !( strlen($temps[2])==mb_strlen($temps[2]) ) ){
						$server_data->result = false;
						$server_data->message = '身體資訊須完整輸入(高.重.奶.歲)，如:160.80.D.21';
						return $server_data;
					}
					$server_data->height = $temps[0];
					$server_data->weight = $temps[1];
					$server_data->cup = $temps[2];
					$server_data->age = $temps[3];
					continue;
				}
				if ( $temp_array[0] == '服務類型' ){
					//業主說 目前只有定點，所以其他乾脆先鎖起來
					$catelog = ['定點','外送','按摩'];
					// $catelog = ['定點',];
					$temps =[];
					$temps = explode('/',$temp_array[1]);

					foreach($temps as $temp){
						if ( !in_array($temp,$catelog) ){
						$server_data->result = false;
						$server_data->message = '服務類型須為 定點或外送或按摩，格式:定點/外送/按摩';
						return $server_data;
						}
					}
					$server_data->service_type = str_replace("/",",",$temp_array[1]);
					continue;
				}
				if ( $temp_array[0] == '特色標籤' ){
					$temp_array[1] = trim($temp_array[1]);
					$temp_array[1] = trim($temp_array[1],'.');
					$temps =[];
					$temps = explode(',',$temp_array[1]);
					if ( isset($temps[1]) ){
						$server_data->result = false;
						$server_data->message = '特色標籤分隔請用.';
						return $server_data;
					}
					$temps =[];
					$temps = explode('.',$temp_array[1]);
					foreach($temps as $temp){
						if (empty($temp)){
							$server_data->result = false;
							$server_data->message = '特色標籤.之間的項目不得為空';
							return $server_data;
						}
					}
					$server_data->description->special_tags = $temps;
					continue;
				}
				$name = '';
				$name = $temp_array[0];
				$server_data->$name = $temp_array[1];
			}
			else{
				$server_data->result = false;
				$server_data->message = '無此項目'.$temp_array[0];
				return $server_data;
			}
		}

		return $server_data;
	}
	private function checkSpecialService($break_line_list,$partner_id_array){
		$result  = (object)[];
		$result->result = true;
		if ( !strstr($break_line_list[1],'partner_id:') ){
			$result->result = false;
			$result->message = '第二行格式為 partner_id:{您的廠商id}';
			return $result;
		}
		$result->input_partner_id = substr($break_line_list[1],-(strlen($break_line_list[1])-11));
		if ( !in_array($result->input_partner_id,$partner_id_array) ){
			$result->result = false;
			$result->message = '您不具有廠商'.$result->input_partner_id.'的身分';
			return $result;
		}
		$ckecked_array = [];
		for ( $i=2;$i<count($break_line_list);$i++ ){
			$result->message = '第'.($i-1).'條特殊方案錯誤:';
			$break_line_list[$i] = str_replace(" ",'',$break_line_list[$i]);
			$temp_array = [];
			$temp_array = explode(':',$break_line_list[$i]);
			if (!isset($temp_array[1])){
				$result->result = false;
				$result->message .= '請依格式填入( {特殊服務品項}:{O或X或A或價格數字}) 例如: 無套:O 毒龍:500';
				return $result;
			}
			if ( $temp_array[1] != 'O' && $temp_array[1] != 'A' && $temp_array[1] != 'X' && !is_numeric($temp_array[1]) ){
				$result->result = false;
				$result->message .= '請依格式填入( {特殊服務品項}:{O或X或A或價格數字}) 例如: 無套:O 毒龍:500';
				return $result;
			}
			$service_list_name = ServiceList::where('name',$temp_array[0])->first();
			if (!$service_list_name){
				$result->result = false;
				$result->message .= $temp_array[0].'無此項目';
				return $result;
			}
			if ( $temp_array[1]=='O' ){
				array_push($ckecked_array, $temp_array[0].':0');
			}
			if ( $temp_array[1]=='A' ){
				array_push($ckecked_array, $temp_array[0].':0');
			}
			if ( is_numeric($temp_array[1]) ){
				array_push($ckecked_array,$break_line_list[$i]);
			}
		}
		$result->ckecked_array = $ckecked_array;
		return $result;
	}
	private function checkService($break_line_list,$partner_id_array){
		$result  = (object)[];
		$result->result = true;
		if ( !strstr($break_line_list[1],'partner_id:') ){
			$result->result = false;
			$result->message = '第二行格式為 partner_id:{您的廠商id}';
			return $result;
		}
		$result->input_partner_id = substr($break_line_list[1],-(strlen($break_line_list[1])-11));
		if ( !in_array($result->input_partner_id,$partner_id_array) ){
			$result->result = false;
			$result->message = '您不具有廠商'.$result->input_partner_id.'的身分';
			return $result;
		}
		$checked_service_array = [];
		for ( $i=2;$i<count($break_line_list);$i++ ){
			$result->message = '第'.($i-1).'條方案錯誤:';
			$break_line_list[$i] = str_replace(" ",'',$break_line_list[$i]);
			$temp_array = [];
			$temp_array = explode('/',$break_line_list[$i]);
			if (!isset($temp_array[3])){
				$result->result = false;
				$result->message .= '請依格式填入(分鐘/次/妹拿/經濟拿+店利): 20/1/1000/200/1600';
				return $result;
			}
			// $company_profit = substr(strstr($temp_array[3],'+'),1,strlen(strstr($temp_array[3],'+'))-1);
			// $temp_array[3] = substr($temp_array[3],0,-(strlen(strstr($temp_array[3],'+'))));
			// if (empty($company_profit)){
			// 	$result->result = false;
			// 	$result->message .= '請在+號後輸入店利';
			// 	return $result;
			// }
			// if(!is_numeric($company_profit)){
			// 	$result->result = false;
			// 	$result->message .= '+號後之店利須為純數字';
			// 	return $result;
			// }
			if ( !is_numeric($temp_array[0]) || !is_numeric($temp_array[1]) || !is_numeric($temp_array[2]) || !is_numeric($temp_array[3]) ){
				$result->result = false;
				$result->message .= '只能填入純數，並依格式填入(分鐘/次/妹拿/經濟拿+店利): 20/1/1000/200/1400';
				return $result;
			}
			if( $temp_array[1] > 9 || $temp_array[1] < 1 ){
				$result->result = false;
				$result->message .= '次數必須為1~9之間';
				return $result;
			}
			if ( $temp_array[2] < 500 ){
				$result->result = false;
				$result->message .= '妹拿不能小於500塊';
				return $result;
			}

			if ( (($temp_array[2]+$temp_array[3])+200) > $temp_array[4] ){
				$result->result = false;
				$result->message .= '回價至少須為'.(($temp_array[2]+$temp_array[3])+200).'否則店賺不到200';
				return $result;
			}
			array_push($checked_service_array,$break_line_list[$i]);
		}
		$result->checked_service_array = $checked_service_array;

		return $result;
	}

	private function setImg($photo_id){
		$Result = [
			'result'=>false,
			'data'=>'',
		];
		$photo_exist = Storage::disk('images')->exists($photo_id.'.jpg');
		if ( $photo_exist ){
			$Result['result']=true;
			$Result['data']=$photo_id.'.jpg';
		}
		else{
			$Result['data']='圖片上傳失敗，請嘗試重新上傳，或聯繫系統工程師';
		}
		return $Result;
	}


}
