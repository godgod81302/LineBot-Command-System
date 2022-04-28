<?php
namespace App\Command;

use App\Model\Server;
use App\Model\PartnerGroupPair;
use App\Model\GroupAdmin;
use App\Model\temp_group_admin;

abstract class BaseCommand implements Command{
		
	protected $command_data = [
		'pre_command' => null,
		'name' => '',
		'cmd' => '',
		'description' => '',
		'args' => [],
		'access' => [],
	];
	
	public function getName() : string {
		return $this->command_data['name'];
	}
	
	public function getCommandData(){
		return $this->command_data;
	}
	
	// 執行指令入口,做指令權控管
	public function execute( $args=null ){
		$message = '';
		if( !$args ){
			return "參數資料錯誤,無法回應";
		}
		elseif( !$args->user ){
			return "無使用者資料,無法回應";
		}
		elseif( !$args->command ){
			return "無指令輸入,無法回應";
		}
		
		$user = $args->user;
		$group = $args->group;
		$command = $args->command;

		// 確認使用者身分權限
		$reason = $this->checkRoleAccess( $group, $user );
		if( $reason )
			return config('app.debug') ? $reason : false;
			
		if( $group->enable=='N' && !isset($this->command_data['ignore_Acess_check']) )
			return config('app.debug') ? '群組未授權' : false;
		// 確認使令在該群組可否使用
		$group_type_reject = $this->checkGroupTypeAccess( $group, $user );
		if( !$group_type_reject )
			return config('app.debug') ? '此指令不可在當前群組使用' : false;

		if( !$group && $this->command_data['pre_command']==CommandManager::USUAL_PRE_COMMAND )
			return config('app.debug') ? '本系統無法對非群組回應' : false;

		if( $this->command_data['pre_command']==CommandManager::CTRL_PRE_COMMAND ){
			$group_admins = $user->group_admins;
			if( !$group_admins )
				return config('app.debug') ? "您不具有管理員身分" : false;
		}

		return $this->process($args);
	}
	// 執行指令入口,做指令權控管
	public function runSessionFunction( $args=null ){
		$message = '';
		if( !$args ){
			return "參數資料錯誤,無法回應";
		}
		elseif( !$args->user ){
			return "無使用者資料,無法回應";
		}
		elseif( !$args->command ){
			return "無指令輸入,無法回應";
		}

		$user = $args->user;
		$group = $args->group;

		$role_reject = $this->checkRoleAccess( $group, $user );
		if( $role_reject ){
			return $rejrole_rejectect;
		}

		$group_type_reject = $this->checkGroupTypeAccess( $group, $user );
		if( !$group_type_reject ){
			return $group_type_reject;
		}
		
		return $this->SessionFunction($args);
	}
	
	abstract protected function SessionFunction( $args=null );
	abstract protected function process( $args=null );
	
	// 檢查該指令是否能在群組內使用
	private function checkGroupTypeAccess( $group, $user){
		$is_acess = false;
		// 沒有設定授權使用群組類型,默認該指令可在群組內使用
		if ( !isset($this->command_data['authorized_group_type']) ){
			return true;
		}

		if ( in_array('Admin',$this->command_data['authorized_group_type']) ){
			// 使用者必須是管理員身分
			$is_group_admin = $user->group_admins->count()>0;
			if ( !$is_group_admin ){
				$is_acess = false;
			}
			$partner_id_array = [];
			foreach ( $user->group_admins as $group_admin ){
				$partner_id_array[] = $group_admin->partner_id;
			}
			//超級管理員給過
			if (in_array(1,$partner_id_array)){
				return true;
			}
			// 群組不在使用者所屬partner配對且非管理員群組,不給使用該指令
			$partner_group_pair = PartnerGroupPair::where('line_group_id',$group->id)->whereIn('partner_id',$partner_id_array)->where('group_type','Admin')->first();
			if ( !$partner_group_pair || !$is_group_admin){
				$is_acess = false;
			}
			else{
				$is_acess = true;
			}
			if ($is_acess){
				return $is_acess;
			}
		}
		if ( in_array('Booking',$this->command_data['authorized_group_type']) && !$is_acess){
			$partner_group_pair = PartnerGroupPair::where('line_group_id',$group->id)->where('group_type','Booking')->first();
			if ( !$partner_group_pair ){
				$is_acess = false;
			}
			else{
				$is_acess = true;
			}
			if ($is_acess){
				return $is_acess;
			}
		}

		if ( in_array('Server',$this->command_data['authorized_group_type']) && !$is_acess){
			$partner_group_pair = PartnerGroupPair::where('line_group_id',$group->id)->where('group_type','Server')->first();
			if ( !$partner_group_pair ){
				$is_acess =  false;
			}
			else{
				$is_acess = true;
			}
			if ($is_acess){
				return $is_acess;
			}
		}
		
		return $is_acess;
	}

	// 檢查使用者在群組內是否有權限使用該指令
	private function checkRoleAccess( $group, $user){
		$is_sys_admin = $user->group_admins->where('partner_id',1)->first()!=null;
		$is_group_admin = $user->group_admins->count()>0;
		$is_temp_admin = temp_group_admin::where('line_user_id',$user->id)->first()!=false;

		$role_data = [
			'admin' => (object)[
				'access' => $is_sys_admin,
				'name' => '系統管理員',
			],
			'group_admin' => (object)[
				'access' => $is_group_admin,
				'name' => '群組管理員',
			],
			'temp_group_admin' => (object)[
				'access' => $is_temp_admin,
				'name' => '臨時群組管理員',
			],
			'sales' => (object)[
				'access' => $user->sales!=null,
				'name' => '業務',
			],
			'broker' => (object)[
				'access' => $user->broker!=null,
				'name' => '經紀人',
			],
			'server' => (object)[
				'access' => $user->server!=null,
				'name' => '服務員',
			],
		];

		$access = false;
		$require_role = "";

		foreach( $role_data as $role => $data ){
			if( in_array($role, $this->command_data['access']) ){
				if( $is_sys_admin || ($is_group_admin && $role!='admin') || $data->access ){
					$access = true;
					break;
				}
				else{
					$require_role .= "{$data->name},";
				}
			}
		}
		$require_role = trim($require_role,',');
		if ( count($this->command_data['access']) == 0 ){
			$access = true;
		}
		if( !$access ){
			return "您不具有[{$require_role}]的身分，指令權限不足";
		}
	}
}