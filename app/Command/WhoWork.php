<?php
namespace App\Command;

use App\Model\Partner;
use App\Model\Server;
class WhoWork extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new WhoWork();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '查服務員勤務狀況',
			'cmd' => '查勤',
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
						
						$message .= $server->name.date('m/d H:i',strtotime($server->start_time)).'~'.date('m/d H:i',strtotime($server->end_time));
					}
					else{
						//進來這裡表示服務員沒有上班，上次下班時間早於早上七點
						$message .= $server->name.'未上班,下班於:'.date('m/d H:i',strtotime($server->end_time));
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
			foreach ( $group_partner_id_array as $group_partner_id ){
				$message = '';
				if (in_array($group_partner_id,$partner_id_array) ){
					$message .= '廠商id:'.$group_partner_id."\n";
					$servers = Server::where('partner_id',$group_partner_id)->orderBy('end_time','desc')->get();
					$index = 1;
					foreach ( $servers as $server ){
						$compare_timestamp = (date('H')>7) ? strtotime(date('Y-m-d 07:00:00')) : (strtotime(date('Y-m-d 07:00:00'))-86400);
						if (strtotime($server->end_time)>$compare_timestamp){
							
							$message .= $server->name.date('m/d H:i',strtotime($server->start_time)).'~'.date('m/d H:i',strtotime($server->end_time));
						}
						else{
							//進來這裡表示服務員沒有上班，上次下班時間早於早上七點
							$message .= $server->name.'未上班,下班於:'.date('m/d H:i',strtotime($server->end_time));
						}
						if ($index != count($servers)){
							$message .= "\n";
						}
						$index++;
					}
				}
			}
		}


    return $message;
	}
	protected function SessionFunction( $args=null ) : string {
		
	}
}