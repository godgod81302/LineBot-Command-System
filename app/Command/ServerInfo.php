<?php
namespace App\Command;

use App\Model\Server;
use App\Model\Booking;

class ServerInfo extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new ServerInfo();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '看資料',
			'cmd' => '看',
			'description' => '查看指定服務員資料',
			'args' => [
				'人名'
			],
			'access' => [],
			'authorized_group_type' => ['Admin'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$message = "格式錯誤(E00),請使用以下格是:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."{人名},例如:\n".
			$this->command_data['pre_command'].$this->command_data['cmd']."花花";
		
		return $message;
	}
	protected function SessionFunction( $args=null ) : string {
		
	}
}
