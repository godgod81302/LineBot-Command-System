<?php
namespace App\Command;

use App\Model\Server;
use App\Model\Booking;

class GetGroupMember extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new GetGroupMember();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '&',
			'name' => '取得群組成員',
			'cmd' => '群組成員',
			'description' => '查看群組內的成員名單',
			'args' => [],
			'access' => ['admin','group_admin'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
	  $message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd'];
		if( strpos($args->command, $this->command_data['cmd'])!==0 )
			return $message;

		$message = "";
		$users = $args->group->users;
		foreach( $users as $user ){
			$message .= "{$user->latest_name}({$user->id})\n";
		}
		$message = trim($message,"\n");

		return $message;
	}
	protected function SessionFunction( $args=null ) : string {
		
	}
}
