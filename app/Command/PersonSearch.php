<?php
namespace App\Command;

use App\Model\Server;
use App\Model\Booking;
use App\Model\Country;
use App\Model\Service;
use App\Model\RoomData;
use App\Model\ServicePoint;
use App\Model\RoomServerPair;

class PersonSearch extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new PersonSearch();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '找人',
			'cmd' => '誰',
			'description' => '搜尋指定人的當前狀態',
			'args' => [
				'人名', '時間'
			],
			'access' => ['admin','group_admin','sales'],
			'authorized_group_type' => ['Booking'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."{人}{{hhmm或'現在'},例如:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."0130";
		
		// print_r($args);
		//test
		$group = $args->group;
		if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return $message;
		}
		
		$partners = $group->partners;
		$partner_id_array = [];
		foreach( $partners as $partner ){
			$partner_id_array[]=$partner->id;
		}
		if( count($partners)==0 )
			return "本群未綁定任何廠商，無法搜尋";

		$command = $args->command;
		$name = $this->getPersonName( $command );
		if( !$name ){
			$message = str_replace('E00','E01',$message);
			return $message;
		}
		
		$time = $this->getTime( $command );
		if( !$time ){
			$message = str_replace('E00','E02',$message);
			return $message;
		}

		$message = '';
		if( $name=='誰' ){
			$servers = Server::whereIn('partner_id',$partner_id_array)->where('enable','<>','N')->orderBy('country_id','asc')->get();
			$servers_id = [];
			foreach($servers as $server)
				$servers_id[] = $server->id;
			
			$message = "{$time} 可排：\n";
			$message .= $this->getFreeServers($time, $servers_id);
		}
		else{
			$message = "搜尋\"{$name}{$time}\"：";
			// 指定人名
			$servers = Server::where('name','like',"%{$name}%")->whereIn('partner_id',$partner_id_array)->where('enable','<>','N')->get();
			if (count($servers)==1){
				$server_service_point_data = CommandUtil::getServerServicePoint($servers->first());
				$message .= '('.$server_service_point_data->name.')';
			}
			$message .= "\n";
			$servers_id = [];
			foreach($servers as $server){
				$servers_id[] = $server->id;
			}

			if( $servers_id ){
				$message .= $this->getFreeServers($time, $servers_id);
			}
			else{
				$servers = Server::where('name','like',"%{$name}%")->whereIn('partner_id',$partner_id_array)->where('enable','N')->get();
				foreach($servers as $server){
					$servers_id[] = $server->id;
				}
				if( $servers_id ){
					$message .= "查無相關名稱的服務人員，或該服務員未啟用";
					// $message .= "查無相關名稱的服務人員，或該服務員未啟用"."\n"."\n".$this->serverData($server);
				}
				else{
					$message .= "查無相關名稱的服務人員";
				}
			}
		}
		return $message;
	}
	
	private function getPersonName( &$command ){
		$substring_index = 0;
		$name = null;
		if( strpos($command,$this->command_data['cmd'])===0 ){
			$name = $this->command_data['cmd'];
			$substring_index = 0+strlen($this->command_data['cmd']);
		}
		else{
			$tmp;
			// 搜尋漢字
			if( preg_match('/[a-zA-Z]*\p{Han}*[\x{3105}-\x{3129}]*[a-zA-Z]*/u',$command,$tmp) ){
				// 搜尋到的漢字在指令最前頭
				if( strpos($command,$tmp[0])===0 )
					$name = $tmp[0];
			}
		}		
		$command = substr($command, strlen($name));
		return $name;
	}
	
	private function getTime( &$command ){
		$time = null;
		$tmp;
		if( preg_match('/[0-9]{4}/',$command,$tmp) ){
			// 搜尋到的時間在指令最前頭
			if( strpos($command,$tmp[0])===0 ){
				$time = $tmp[0];
			}
		}
		else if( strpos($command,'現在')===0 ){
			$time = '現在';
		}
		else if( strpos($command,'目前')===0 ){
			$time = '目前';
		}
		else if( strpos($command,'now')===0 ){
			$time = 'now';
		}
		else{
			$time = '現在';
		}
		
		$subcommand = substr($command,strlen($time));
				
		$command = $subcommand;
		return $time;
	}

	private function getFreeServers( $time, $servers_id=[]){	
		$single_server = false;
		if ( count($servers_id)==1 ){
			$single_server = true;
		}
		$message = '';
		$search_timestamp = time();
		// 有指定時間
		if( $time!='目前' && $time!='現在' && $time!='now' ){
			$search_timestamp = strtotime($time);
			if( !$search_timestamp ){
				$message = '時間格式錯誤,無法轉換 => '.$time;
				return $message;
			}
		}
		if ( date('H')>config('app.system.day_split_hour') ){
			$search_timestamp = (strtotime(date('Y-m-d H:i',$search_timestamp)) > strtotime(date('Y-m-d',time()).' '.config('app.system.day_split_hour').':00' ) ) ? $search_timestamp : $search_timestamp+24*60*60;
		}
		
		$search_datetime = date('Y-m-d H:i:s', $search_timestamp);
		$work_time_result = CommandUtil::getWorkDayTime();
		$server_not_ok = [];
		//如果沒上班就從列表裡拔掉
    foreach ( $servers_id as $server_id ){
			$server = Server::where('id',$server_id)->get();
			if ( !empty($server->first()->end_time)){
				$is_server_between = $server->where('end_time', '>', $search_datetime)->where('start_time','<=',$search_datetime)->first();
				if (!$is_server_between){
					if ( !(strtotime($work_time_result->end_time)>strtotime($server->first()->start_time)) || !(strtotime($server->first()->end_time)>strtotime($work_time_result->start_time)) ){
						array_splice($servers_id,array_search($server_id,$servers_id),1);
						$server_not_ok[] = $server_id;
					}
					else if (strtotime($search_datetime)>strtotime($server->first()->end_time)){
						array_splice($servers_id,array_search($server_id,$servers_id),1);
						$server_not_ok[] = $server_id;
					}
				}
			}
			else{
				if ( strtotime($work_time_result->start_time) < strtotime($server->first()->start_time) ){
					$is_server_between = $server->where('start_time','<=', $search_datetime)->first();
					if (!$is_server_between){
						array_splice($servers_id,array_search($server_id,$servers_id),1);
						$server_not_ok[] = $server_id;
					}
				}
				else{
					array_splice($servers_id,array_search($server_id,$servers_id),1);
					$server_not_ok[] = $server_id;
				}
			}
		}
		
		if( count($servers_id)>0 ){
			// 獲取 排除已占用的所有Server,並確認是否有空閒
			$servers = Server::whereIn('id',$servers_id)->get();
			
			$servers = CommandUtil::sortServerByServicePointAndCountry($servers);

			$service_point_name = '';
			$index = 1;
			//這是用來給 #誰 查多人時 當前時間無法全方案，就記錄起來，在該據點最後做補充
			$temp_message = '';
			foreach($servers as $server){
				$server_service_point_data = CommandUtil::getServerServicePoint($server);
				$server = Server::where('id',$server->id)->first();
				$is_server_free_on_time = false;
				$result = CommandUtil::getSingalFreeServerOnSpecificTime($server,$search_datetime);
				if (count($result->periods)==0){
					$is_server_free_on_time = true;
				}
				if ( !$single_server ){
					$server_service_point_name = $server_service_point_data->name;
					if ( $service_point_name != $server_service_point_name ){
						if (!empty($temp_message)){
							$message .= $temp_message;
							$temp_message = '';
						}
						$service_point_name  = $server_service_point_name;
						if ($index!=1){
							$message .= "\n";
						}
						$message .= $service_point_name."\n";
					}
					$country_name = Country::where('id',$server->country_id)->first()->name;
					$country_name = mb_substr($country_name,0,1);
					
					if (count(array_unique($result->periods))!=count(array_unique($server->services->where('description','<>','特殊服務')->where('status','<>','hide')->pluck('period')->all()))){
						unset($result);
						$result = CommandUtil::getServerNextFreeTime($server,$search_datetime);
						$result->message = trim($result->message,"\n");
						$temp_message .= $country_name.')';

						if ( strpos($result->message,"\n") != false ){
							$tmp_array = [];
							$tmp_array = explode("\n",$result->message);
							$tmp_array[1] = mb_substr($tmp_array[1],mb_strlen($server->name),mb_strlen($tmp_array[1]));
							$temp_message .= $tmp_array[0]."\n".'('.$tmp_array[1].')';
						}
						else{
							$temp_message .= $result->message;

						}
						$temp_message .= "\n";
						if ($index == count($servers)){
							$message .= $temp_message;
						}

						$index++;
						continue;
					}
					$message .= $country_name.')';
					$message .= $server->name;

					$periods = array_unique($result->periods);
					foreach ( $periods as $key => $period ){
						if ( $period != end($periods) ){
							$message .= $period.'/';
						}
						else{
							$message .= $period."\n";
						}

					}
					if ($index == count($servers)){
						$message .= $temp_message;
					}
					$index++;
				}
				else{
					if (count(array_unique($result->periods))!=count(array_unique($server->services->where('description','<>','特殊服務')->where('status','<>','hide')->pluck('period')->all()))){
						//這行是表示當下可以的方案
						// $message .= $result->message."\n";
						unset($result);
						$result = CommandUtil::getServerNextFreeTime($server,$search_datetime);
						$message .= $result->message;
						// $message .= "\n";
						// $message .= "\n".$this->serverData($server);
						continue;
					}

					$message .= $server->name;
					$periods = array_unique($result->periods);
					foreach ( $periods as $key => $period ){
						if ( $period != end($periods) ){
							$message .= $period.'/';
						}
						else{
							$message .= $period."\n";
						}

					}
					// $message .= "\n".$this->serverData($server);
				}
			}

			return $message;
		}
		else{
			if (count($server_not_ok)==1){
				$not_ok_server = Server::where('id',$server_not_ok[0])->first(); 
				return '無服務員資訊，或者目標服務員非上班時段';
				// return '無服務員資訊，或者目標服務員非上班時段'."\n"."\n".$this->serverData($not_ok_server);
			}
			else{
				return '無服務員資訊，或者目標服務員非上班時段';
			}

		}

	}
	protected function SessionFunction( $args=null ){
		
	}
	private function serverData($server){
    $message = '';

    // $message .= 'partner_id:'.$server->partner_id."\n";
    // $message .= 'broker_id:'.$server->broker_id."\n";
		$message .= 'name:'.$server->name."\n";
    $contry = Country::where('id',$server->country_id)->first();
		$server_service_point_data = CommandUtil::getServerServicePoint($server);
		$message .= '據點:'.$server_service_point_data->name."\n";
    $message .= '國籍:'.$contry->name."\n";
    $message .= '語言:'.$server->lanague."\n";
    $message .= '身體資訊:'.$server->height.'.'.$server->weight.'.'.$server->cup.'.'.$server->age."\n";
    // $message .= '服務類型:'.$server->service_type."\n";
    $description_array = json_decode($server->description,true);

    $services = Service::where('server_id',$server->id)->where('description',"<>",'特殊服務')->where('name','<>','自慰秀')->where('status','<>','hide')->orderBy('period')->get();
    if ( count($services)>0 ){
      //{分鐘}/{次數}/{妹拿}/{經濟拿}/{店利}
      $message .= '方案(分鐘/次/回價):'."\n";
      foreach( $services as $service ){
        $basic_price=$service->server_fee
        +$service->broker_fee
        -$service->company_cost
        +$service->company_profit
        -$service->marketing_cost
        +$service->sales_profit;

        $message .= $service->period.'/'.$service->s_time.'/'.'回'.$basic_price."\n";
      }
    }
		if ( isset($description_array['special_tags'])){
      $message .= '特色標籤:';
      foreach ( $description_array['special_tags'] as $tag ){
        if ( $tag!=end($description_array['special_tags'])){
          $message .= $tag.'/';
        }
        else{
          $message .= $tag;
        }
      }
      $message .= "\n";
    }
		$message .= '特殊服務:'."\n";
		$fore_special_array = ['殘廢澡','無套吹','戴套做'];
		$is_fore_special_exist = Service::where('server_id',$server->id)->where('description','特殊服務')->where('status','<>','hide')->whereIn('name',$fore_special_array)->get();
    if ( count($is_fore_special_exist)>0 ){
			$index = 1;
			foreach( $is_fore_special_exist as $fore_special ){
				if ($fore_special->server_fee >0){
					$message .= $fore_special->name.'+'.$fore_special->server_fee;
					$message .= "/";
				}
				else{
					$message .= $fore_special->name;
					$message .= "/";
				}
				$index++;
			}
		}
		$msg_en_special_service_not_free = '';
		$msg_en_special_service_free = '';
    //特別服務
    $special_services_free = Service::where('server_id',$server->id)->where('description','特殊服務')->where('status','<>','hide')->whereNotIn('name',$fore_special_array)->where('server_fee',0)->orderBy('name')->get();
		$is_end_line_exist  = false;
    if ( count($special_services_free)>0 ){
			$index = 1;
      foreach( $special_services_free as $value ){
				if (!preg_match("/[\x7f-\xff]/", $value->name)) {
					$msg_en_special_service_free .= $value->name.'/';
					continue;
				}
				$message .= $value->name;
				if ($index != count($special_services_free)){
					$message .= "/";
				}
				$index++;
      }
    }
    $special_services_not_free = Service::where('server_id',$server->id)->where('description','特殊服務')->where('status','<>','hide')->whereNotIn('name',$fore_special_array)->where('server_fee','>',0)->orderBy('name')->get();
    if ( count($special_services_not_free)>0 ){
			$index = 1;
			if ( count($special_services_free)!=0 ){
				$message .= "/";
			}
      foreach( $special_services_not_free as $value ){
				if (!preg_match("/[\x7f-\xff]/", $value->name)) {
					$msg_en_special_service_not_free .= $value->name.'+'.$value->server_fee.'/';
					continue;
				}
        $message .= $value->name.'+'.$value->server_fee;
				if ($index != count($special_services_not_free)){
					$message .= "/";
				}
				$index++;
      }
    }
		if (!empty($msg_en_special_service_free)){
			$msg_en_special_service_free = trim($msg_en_special_service_free,"/");
			if (substr($message,-1)!='/'){
				$message .= '/';
			}
			$message .= $msg_en_special_service_free;
		}
		if (!empty($msg_en_special_service_not_free)){
			$msg_en_special_service_not_free = trim($msg_en_special_service_not_free,"/");
			if (!empty($msg_en_special_service_free)){
				$message .= '/';
			}
			$message .= $msg_en_special_service_not_free;
		}
    return $message;
  }

}
