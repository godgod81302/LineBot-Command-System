<?php
namespace App\Command;

use App\Model\Partner;
use App\Model\Server;
use App\Model\Country;
class AreaServerList extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new AreaServerList();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '查服務員勤務狀況',
			'cmd' => '查班別',
			'description' => '列出所有服務員上班時間以及預計下班時間',
      'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Admin'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$user = $args->user;
		$group = $args->group;
		$command = $args->command;

    $group = $args->group;
		if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return $message;
		}

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
		$message = '您雖具有群組管理員身分，但並非此群組綁定之合作廠商，如有疑問請詢問系統服務商';
		//過濾指令字
		$command_msg = mb_substr($command, mb_strlen($this->command_data['cmd']));
		if ( $super_admin_acess ){
			$message = '最高管理員您好，因您不具有任一廠商群組管理員身分，且未指定查哪個廠商id的勤(如:#查勤2)，故無法查詢';
			if (is_numeric($command_msg)){
				$partner = Partner::where('id',$command_msg)->first();
				if (!$partner){
					return '抱歉，該id找不到相關廠商';
				}
				$servers = Server::where('partner_id',$partner->id)->get();
				$message = '廠商id:'.$partner->id."\n";
				$index=1;
				foreach ( $servers as $server ){
					$compare_timestamp = (date('H')>7) ? strtotime(date('Y-m-d 07:00:00')) : (strtotime(date('Y-m-d 07:00:00'))-86400);
					if (strtotime($server->end_time)>$compare_timestamp){
						$time1 = date('m/d H:i',strtotime($server->start_time));
						$time2 = date('m/d H:i',strtotime($server->end_time));
						if ( date('H',strtotime($server->start_time))=='00' ){
							$time1 = date('m/d',strtotime($server->start_time)-86400).' 24:'.date('i',strtotime($server->start_time));
						}
						if ( date('H',strtotime($server->end_time))=='00' ){
							$time2 = date('m/d',strtotime($server->end_time)-86400).' 24:'.date('i',strtotime($server->end_time));
						}
						
						$message .= $server->name.$time1.'~'.$time2;
					}
					else{
						//進來這裡表示服務員沒有上班，上次下班時間早於早上七點
						$time2 = date('m/d H:i',strtotime($server->end_time));
						if ( date('H',strtotime($server->end_time))=='00' ){
							$time2 = date('m/d',strtotime($server->end_time)-86400).' 24:'.date('i',strtotime($server->end_time));
						}
						$message .= $server->name.'未上班,下班於:'.$time2;
					}
					if ($index != count($servers)){
						$message .= "\n";
					}
					$index++;
				}
				return $message;
			}
		}
		if ( $admin_access ){


      $servers = Server::whereIn('partner_id',$partner_id_array)->get();
			$servers = CommandUtil::sortServerByServicePointAndCountry($servers);
      $service_point_name = '';
      $message  = '服務員清單:'."\n";
      $index = 1;
			$temp_message = '';
      foreach ( $servers as $server ){
				$server_service_point_data = CommandUtil::getServerServicePoint($server);
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
        $message .= $country_name.')'.$server->name;
        if (!empty($server->duty_start_time) && !empty($server->duty_end_time)){
          $message .= $server->duty_start_time.'~'.$server->duty_end_time;
        }
        if ($server->enable == 'N'){
          $message .= '(禁用';
        }
        $message .= "\n";
        $index++;
      }

		}


    return $message;
	}
	protected function SessionFunction( $args=null ) : string {
		
	}
}