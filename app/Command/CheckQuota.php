<?php
namespace App\Command;

use App\Line\ApiHelper;
class CheckQuota extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new CheckQuota();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '查扣打',
			'cmd' => '查扣打',
			'description' => '查扣打',
			'args' => [
				'人名'
			],
			'access' => ['admin','group_admin'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {

    $group = $args->group;
    if( !$group || $group->enble=='N' ){
      $message = "未授權";
      return $message;
    }
    $helper = ApiHelper::helper(env('LINE_BOT_CHANNEL_ACCESS_TOKEN'));
    $result = $helper->getNumberReplyMessageSend('2021-07-05');

		return $result;
	}
	protected function SessionFunction( $args=null ) : string {
		
	}
}
