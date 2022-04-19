<?php
namespace App\Command;

use App\Model\Calendar;
use App\Model\Flag;
use App\Model\PartnerGroupPair;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverOptions;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use DB;

class GoogleHomeBroadcast extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new GoogleHomeBroadcast();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '廣播',
			'cmd' => '廣播',
			'description' => 	$this->command_data['pre_command'].$this->command_data['cmd'],
			'args' => [
				'廠商ID','LineUserID','動作'
			],
			'access' => ['admin','group_admin'],
			'authorized_group_type' => ['Admin'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$user = $args->user;
		$group = $args->group;
		$command = $args->command;

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
			
		$command = substr($command,strlen( $this->command_data['cmd']));
		$headers = array(
			'Content-Type: multipart/form-data',
			'Authorization: Bearer QfJtDAozUvpIFe0hISzqVHXd92z5zlcazmanOtoQQoO'
		);
    $message = 'rrrr好爽';



		return $message;
	}
	protected function SessionFunction( $args=null ) : string {
		
	}
}
