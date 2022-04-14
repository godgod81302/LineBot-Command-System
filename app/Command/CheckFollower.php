<?php
namespace App\Command;

use App\Line\ApiHelper;
class CheckFollower extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new CheckFollower();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '查粉絲',
			'cmd' => '查粉絲',
			'description' => '查粉',
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

    $result = $helper->getGroupMemberIds($group->id);
    print_r($result);exit;
		return $result;
	}
	protected function SessionFunction( $args=null ) : string {
		
	}
}
