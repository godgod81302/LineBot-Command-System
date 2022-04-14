<?php
namespace App\Command;

use App\Model\Partner;
use App\Model\GroupAdmin;
use App\Model\LineUser;
use App\Model\PartnerGroupPair;

class SetGroupAdmin extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new SetGroupAdmin();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '&',
			'name' => '設定管理員',
			'cmd' => '綁管理',
			'description' => 	$this->command_data['pre_command'].$this->command_data['cmd']."{廠商ID}{一字暱稱}(空格){tag某人}(空格){上|下},例如:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."2胖 @某人".
			$this->command_data['pre_command'].$this->command_data['cmd']."2帥 @某人 下",
			'args' => [
				'廠商ID','LineUserID','動作'
			],
			'access' => ['admin','group_admin'],
			'authorized_group_type' => ['Admin','Booking'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$user = $args->user;
		$group = $args->group;
		$command = $args->command;
		$mentionees = $args->mention->mentionees;
		$PartnerGroupPairs = PartnerGroupPair::where('line_group_id',$group->id)->get();
    if ( count($PartnerGroupPairs) == 0 ){
      return '本群組未綁定任何廠商，請先綁定';
    }
		$is_group_admin = $user->group_admins->count()>0;
		if ( !$is_group_admin ){
			return '您不具有群組管理員身分';
		}
		$partner_id_array = [];
		foreach ( $user->group_admins as $group_admin ){
			$partner_id_array[] = $group_admin->partner_id;
		}
		if (count($mentionees)>1){
			return 'tag人數至多一位，新增程序已中斷';
		}
		$user_id = '';
		foreach ( $mentionees as $mention){
			if (empty($mention->userId)){
				return 'tag的第'.$mention->index.'位沒有設定line id，新增程序已中斷';
			}
			$user_id = $mention->userId;
		}
		$message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."{廠商ID}{一字暱稱}(空格){tag某人}(空格){上|下},例如:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."2胖 @某人".
			$this->command_data['pre_command'].$this->command_data['cmd']."2帥 @某人 下";
			
		$command = substr($command,strlen( $this->command_data['cmd']));
		$partner_id = substr($command,0,1);
		if( !preg_match('/\d/',$partner_id) )
			return "廠商ID錯誤，必須是整數";
		if ( !in_array($partner_id,$partner_id_array) )
			return '您不具有廠商id:'.$partner_id.'之管理員身分，故無法新增其他管理員';
		$partner = Partner::find($partner_id);
		if( !$partner )
			return "查無編號#{$partner_id}的廠商";
		$nickname = mb_substr($command,1,1);
		if( !preg_match('/^([\x7f-\xff]+)$/',$nickname) )
			return '一字暱稱須為中文';
		if( !preg_match('/^U[0-9a-zA-Z]{32}/',$user_id) )
			return "LineUserID格式不正確";
		$user = LineUser::find($user_id);
		if( !$user )
			return "查無LineUserID[{$user_id}]的使用者";
		
		if (substr($command,-2)!=' 下'){
			$action = '上';
		}
		else{
			$action = '下';
		}
		if( !preg_match('/上|下/u',$action) )
			return "請制訂是要綁定(上)還是解綁定(下)";

		if( $action=='上' ){
			$is_admin_exist = GroupAdmin::where('partner_id',$partner_id)->where('line_user_id',$user_id)->first();
			if ($is_admin_exist){
				return '管理員已存在，無須法再次新增';
			}

			$admin = new GroupAdmin();
			$admin->partner_id = $partner_id;
			$admin->line_user_id = $user_id;
			$admin->nickname = $nickname;
			$insert_result = $admin->save();
			if ($insert_result){
				return "已將使用者[{$user->latest_name}({$user->id})]設定為廠商[{$partner->name}({$partner->id})]的管理員";
			}
			else{
				return '管理員新增失敗';
			}
		}
		elseif( $action=='下' ){
			$admin = GroupAdmin::where('partner_id',$partner_id)->where('line_user_id',$user_id)->get()->first();
			if( $admin )
				$admin->delete();
			return "已將使用者[{$user->latest_name}({$user->id})]從廠商[{$partner->name}({$partner->id})]的管理員中移除";
		}

		return $message;
	}
	protected function SessionFunction( $args=null ) : string {
		
	}
}
