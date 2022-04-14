<?php
namespace App\Command;

use App\Model\Server;
use App\Model\Booking;
use App\Line\ApiHelper;

class WaitServer extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new WaitServer();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '等服務員',
			'cmd' => '等',
			'description' => '客已到，需要等服務員一下',
			'args' => [],
			'access' => ['admin','group_admin','server'],
			'authorized_group_type' => ['Server'],
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

    
    $helper = ApiHelper::helper(env('LINE_BOT_CHANNEL_ACCESS_TOKEN'));
    $messages = [
        [	'type' => 'text',	'text' =>  $msg ],
    ];
    $result = $helper->push($server->line_group_id, $messages, true);
    
		return $message;
	}
	protected function SessionFunction( $args=null ) : string {
		
	}
}
