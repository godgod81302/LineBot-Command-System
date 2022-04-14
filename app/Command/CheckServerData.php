<?php
namespace App\Command;

use App\Model\Partner;
use App\Model\Server;
use App\Model\Country;
use App\Model\Service;
use App\Model\RoomData;
use App\Model\ServicePoint;
use App\Model\RoomServerPair;

class CheckServerData extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new CheckServerData();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '查服務員',
			'cmd' => '查服務員',
			'description' => '查服務員，格式為:#查服務員{服務員名稱}',
			'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Admin'],
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
		//過濾指令字
    $command_msg = substr($command, strlen($this->command_data['cmd']));
    if( preg_match('/[a-zA-Z]*\p{Han}*[\x{3105}-\x{3129}]*[a-zA-Z]*/u',$command_msg,$tmp) && !empty($command_msg)){
			// 搜尋到的漢字在指令最前頭
			if( strpos($command_msg,$tmp[0])===0 ){
				$name = $tmp[0];
			}

    }
    if (!empty($name)){
      $partner_id = substr($command_msg,strlen($name),strlen($command_msg));
      if ( !empty($partner_id) ){
        if ( !is_numeric($partner_id) ){
          return '名字後須加上廠商數字id';
        }
        if (!in_array($partner_id,$partner_id_array)){
          return '您不具有'.$partner_id.'之廠商身分';
        }
        $server = Server::where('name',$name)->where('partner_id',$partner_id)->first();
        if (!$server){
          return '抱歉，未查找到指定服務員的資料';
        }
        else{
          $message = $this->serverData($server);
          return $message;
        }
      }
      else{
        $server = Server::where('name',$name)->whereIn('partner_id',$partner_id_array)->first();
        if (!$server){
          return '抱歉，未查找到指定服務員的資料';
        }
        else{
          $message = $this->serverData($server);
          return $message;
        }
      }
    }
    else{
      $servers = Server::whereIn('partner_id',$partner_id_array)->get();
      if (count($servers)==0){
        return '當前沒有服務員';
      }
      $message ='';
      foreach ( $servers as $server ){
        $message .= $this->serverData($server);
        $message .= '--------'."\n";
      }
      return $message;
    }
    


		return $message;
	}
	protected function SessionFunction( $args=null ) : string {
		
  }
  private function serverData($server){
    $message = '';
    $message .= 'name:'.$server->name."\n";
    $message .= 'partner_id:'.$server->partner_id."\n";
    $message .= 'broker_id:'.$server->broker_id."\n";
		$server_service_point_data = CommandUtil::getServerServicePoint($server);
		$message .= '據點:'.$server_service_point_data->name."\n";

    $contry = Country::where('id',$server->country_id)->first();
    $message .= '國籍:'.$contry->name."\n";
    $message .= '語言:'.$server->lanague."\n";
    $message .= '身體資訊:'.$server->height.'.'.$server->weight.'.'.$server->cup.'.'.$server->age."\n";
    $message .= '服務類型:'.$server->service_type."\n";
    $description_array = json_decode($server->description,true);
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
    $services = Service::where('server_id',$server->id)->where('description',"<>",'特殊服務')->orderBy('period')->get();
    if ( count($services)>0 ){
      //{分鐘}/{次數}/{妹拿}/{經濟拿}/{店利}
      $message .= '方案(分鐘/次/妹拿/經濟拿/回價):'."\n";
      foreach( $services as $service ){
        $basic_price=$service->server_fee
        +$service->broker_fee
        -$service->company_cost
        +$service->company_profit
        -$service->marketing_cost
        +$service->sales_profit;

        $message .= $service->period.'/'.$service->s_time.'/'.$service->server_fee.'/'.$service->broker_fee.'/'.$basic_price."\n";
      }
    }
    //特別服務
    $special_services = Service::where('server_id',$server->id)->where('description','特殊服務')->orderBy('name')->get();
    if ( count($special_services)>0 ){
      $message .= '特殊服務:'."\n";
      foreach( $special_services as $special_service ){
      if (!preg_match("/[\x7f-\xff]/", $special_service->name)) {
        continue;
      }
        $message .= $special_service->name.':'.$special_service->server_fee."\n";
      }
      foreach( $special_services as $special_service ){
        if (!preg_match("/[\x7f-\xff]/", $special_service->name)) {
          $message .= $special_service->name.':'.$special_service->server_fee."\n";
        }
        else{
          continue;
        }
        }
    }
    return $message;
  }
}
