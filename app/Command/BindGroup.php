<?php
namespace App\Command;
use App\Model\LineGroup;
use App\Model\PartnerGroupPair;

class BindGroup extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new BindGroup();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '&',
			'name' => '綁定群組',
			'cmd' => '綁定群組',
			'description' => '設定群組管理員',
			'args' => [
				'partner_id'
			],
			'access' => ['admin','group_admin'],
			'ignore_Acess_check'=>true,
			'group_auth' => false,
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$group = $args->group;
		$user = $args->user;
		$command = $args->command;
		
		$is_sys_admin = $user->group_admins->where('partner_id',1)->first()!=null;
		
		$message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."{廠商ID}{類型(Booking/Admin/Server)}[{上(預設)/下}，例如：\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."2Booking";
			$this->command_data['pre_command'].$this->command_data['cmd']."2Booking下";
		
		if( strpos($command,$this->command_data['cmd'])!==0 )
			return $message;
		
		$command = substr($command, strlen($this->command_data['cmd']));
		
		// 沒有指定廠商編號,抱錯
		$match = [];
		if( !preg_match('/^[0-9]+/', $command, $match) ){
			$message = str_replace('E00','E01',$message);
			return $message;
		}
		
		$partner_id = $match[0];
		$command = substr($command,strlen($partner_id));
		
		$match = [];
		if( !preg_match('/^[a-zA-Z]+/', $command, $match) ){
			$message = str_replace('E00','E01',$message);
			return $message;
		}
		$group_type = $match[0];
		$command = substr($command,strlen($group_type));

		if ( $group_type!='Booking' && $group_type!='Admin' && $group_type!='Server' ){
			return '請輸入群組類型，&啟用群組{類型(Booking/Admin/Server)}{上/下}'."\n".'例如: &綁定群組2Booking上';
		}

		// 確認群組管理員屬於指定廠商
		$access = false;
		foreach( $user->group_admins as $admin ){
			if( $admin->partner_id==$partner_id ){
				$access = true;
				break;
			}
		}
		
		if( !$access && !$is_sys_admin )
			return "您不是編號廠商#{$partner_id}的群組管理員，無法將群組與編號廠商#{$partner_id}綁訂或解綁";
		
		$partner_group_pair = PartnerGroupPair::where('partner_id',$partner_id)
			->where('line_group_id', $group->id)
			->first();
		if( $command=="" || strpos($command, '上')===0 ){
			$partner_group_pair = PartnerGroupPair::updateOrCreate(
				['line_group_id'=>$group->id,'partner_id'=>$partner_id],
				['group_type'=>$group_type]
			);
			if ( $partner_group_pair ){
				$line_group = LineGroup::where('id',$group->id)->update(['enable'=>'Y']);
				$message = "本群組已與編號廠商#{$partner_id}綁訂，且群組類型設為{$group_type}"."\n";
				if( $group->partners->count()>0 ){
					$message .= "同時已啟用此群組";
				}
					
			}
			else{
				$message = '更新失敗，請通知系統工程師';
			}
		}
		elseif( strpos($command, '下')===0 ){
			if( $partner_group_pair )
				$partner_group_pair->delete();
			$message = "本群組與編號廠商#{$partner_id}的綁訂已解除"."\n";
			if( $group->partners->count()>1 ){
				$message .= "※群組目前綁定".$group->partners->count()."個廠商"."\n";
			}
			else{
				$line_group = LineGroup::where('id',$group->id)->update(['enable'=>'N']);
				$message .= "※群組目前沒綁定廠商，因此群組也已禁用";
			}	
		}
		else{
			$message = str_replace('E00','E02',$message);
			return $message;
		}
		
		return $message;
	}
	protected function SessionFunction( $args=null ) : string {
		
	}
}
