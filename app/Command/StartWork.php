<?php
namespace App\Command;

use App\Model\Server;

use Illuminate\Support\Facades\Redis;

class StartWork extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new StartWork();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '服務員上班',
			'cmd' => '上班',
			'description' => '服務員更新上班時間，並開啟往後推七天的schedule_unit',
      'access' => ['admin','group_admin','server'],
      'authorized_group_type' => ['Admin','Server'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$user = $args->user;
		$group = $args->group;
		$command = $args->command;
    // $command = '上班Test泡泡';
    $group = $args->group;
		if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return $message;
		}
		//過濾指令字
		$command_msg = mb_substr($command, mb_strlen($this->command_data['cmd']));

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

		$message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."{服務員名稱}{廠商編號}";

    if ( $admin_access || $super_admin_acess ){
      //管理員代訂
      $match = [];
      if( preg_match('/^[0-9]+/', $command_msg, $match) ){
        $server_name = substr($command_msg,0,strlen($command_msg)-strlen($match[0])-1);
        $partner_id = $match[0];
        $server = Server::where('name',$server_name)->where('partner_id',$partner_id)->first();
        if (!$super_admin_acess){
          $partner_id_array = [];
          foreach ( $user->group_admins as $group_admin ){
            $partner_id_array[] = $group_admin->partner_id;
          }
          if (!in_array($partner_id,$partner_id_array)){
            return  '您不具有廠商id:'.$partner_id.'之管理員身分';
          }
        }
        if (!$server){
          return '廠商id:'.$partner_id.'找不到指定服務員'.$server_name;
        }
      }
      else{
        $servers = Server::where('name',$command_msg)->get();
        if (count($servers)>1){
          $message = '由於未提供廠商代號，且名稱為:'.$command_msg.'的服務員不只一名，請查明後重新輸入';
          if ($super_admin_acess){
            $message = '查詢到不只一名服務員，請明確指定廠商代號及服務員'."\n";
            foreach( $servers as $server ){
             $message .= $server->name.'-'.$server->partner_id."\n";
            }
          return $message;
          }
        }
        if (count($servers)==0){
          if (!$group->server && !empty($command_msg)){
            return '查無名稱為'.$command_msg.'的服務員';
          }
        }
        //到這表示該名稱服務員只有一個
        if (empty($command_msg)){
          $server = Server::where('id',$group->server->id)->first();
        }
        else{
          $server = Server::where('name',$command_msg)->first();
          if (!$server){
            return '查無名稱為'.$command_msg.'之服務員';
          }
        }

        if ( $group->server->id != $server->id){
          return '該群組服務員為:'.$group->server->name.'，不可替其他服務員喊上班';
        }
        $partner_id = $server->partner_id;
        if (!$super_admin_acess){
          $partner_id_array = [];
          foreach ( $user->group_admins as $group_admin ){
            $partner_id_array[] = $group_admin->partner_id;
          }
          if (!in_array($partner_id,$partner_id_array)){
            return  '您不具有該服務員所屬之廠商id:'.$partner_id.'之管理員身分';
          }
        }

      }
      // $work_start_time = date('Y-m-d 07:i:s');
    }
    else{
      //進入這邊表示 服務員本人喊上班
      $server = Server::where('line_user_id',$user->id)->where('line_group_id',$group->id)->first();
      if (!$server){
        return '您在此群組不具有服務員身分，請通知群組管理員協助';
      }
      $work_start_time = date('Y-m-d H:i:00',strtotime("now")-600);
    }

    if ( empty($server->duty_end_time) ){
      return '服務員未設預設下班時間，無法上班';
    }
    //上班時間如果小於今天，表示上班時間是昨天，所以要更新
		if ( (strtotime($server->start_time) < strtotime(date('Y-m-d 00:00:00'))) && date('H')>7 ){
			$result = CommandUtil::scheduleUnitSeeds(date('Y-m-d 07:00:00'),$server->id);

      $server = Server::where('id',$server->id)->first();
      $server->start_time = date('Y-m-d H:i:00',strtotime("now")-600);
      $end_time = date('Y-m-d ').$server->duty_end_time;
      if (substr($server->duty_end_time,0,2)<config('app.system.day_split_hour')){
        $end_time = date('Y-m-d ',strtotime("+1 day")).$server->duty_end_time;
      }
      $server->end_time = $end_time;
      $results = $server->save();

      $message = '服務員'.$server->name.'於'.date('Y-m-d H:i:00',strtotime("now")-600).'開始上班';
		}
    else{
      //上班時間如果大於今天，若該服務員今天已經喊過下班，則還是可以再次上班
      if ( date('H')>7 ){
        if (strtotime($server->end_time)>strtotime(date('Y-m-d 07:00:00'))){
          $result = CommandUtil::scheduleUnitSeeds(date('Y-m-d H:i:00',strtotime("now")-600),$server->id);
          $server = Server::where('id',$server->id)->first();
          $server->start_time = date('Y-m-d H:i:00',strtotime("now")-600);
          $end_time = date('Y-m-d ').$server->duty_end_time;
          if (substr($server->duty_end_time,0,2)<config('app.system.day_split_hour')){
            $end_time = date('Y-m-d ',strtotime("+1 day")).$server->duty_end_time;
          }
          $server->end_time = $end_time;
          $results = $server->save();
          return '服務員'.$server->name.'於'.date('Y-m-d H:i:00',strtotime("now")-600).'開始上班';
        }
      }
      else{
        $yesterday_morning_timestamp = strtotime(date('Y-m-d 07:00:00'))-86400;
        if (strtotime($server->end_time)>($yesterday_morning_timestamp)){
          $result = CommandUtil::scheduleUnitSeeds(date('Y-m-d H:i:00',strtotime("now")-600),$server->id);
          $server = Server::where('id',$server->id)->first();
          $server->start_time = date('Y-m-d H:i:00',strtotime("now")-600);
          $end_time = date('Y-m-d ').$server->duty_end_time;
          if (substr($server->duty_end_time,0,2)<config('app.system.day_split_hour')){
            $end_time = date('Y-m-d ',strtotime("+1 day")).$server->duty_end_time;
          }
          $server->end_time = $end_time;
          $results = $server->save();
          return '服務員'.$server->name.'於'.date('Y-m-d H:i:00',strtotime("now")-600).'開始上班';
        }
      }
      return '無法使用此指令，因為服務員已上班，上班開始時間為:'.$server->start_time."\n".'下班時間為:'.$server->end_time;
    }



		return $message;
  }
  //尚未檢查業務群裡面的人員
	protected function SessionFunction( $args=null ) : string {

  }
  


}
