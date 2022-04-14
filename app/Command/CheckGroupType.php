<?php
namespace App\Command;

use App\Model\PartnerGroupPair;
use App\Model\Server;
use App\Model\LineUser;

class CheckGroupType extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new CheckGroupType();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '查群組類型',
			'cmd' => '查群組',
			'description' => '查看群組類型，屬於服務或管理或下定群組',
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
    $PartnerGroupPairs = PartnerGroupPair::where('line_group_id',$group->id)->get();
    if ( count($PartnerGroupPairs) == 0 ){
      return '本群組無相關資料，請通知系統工程師';
    }
    $message = '';
    foreach ( $PartnerGroupPairs as $PartnerGroupPair ){
      if ( $PartnerGroupPair->group_type == 'Server' ){
        $server = Server::where('line_group_id',$group->id)->first();
        $line_user = LineUser::where('id',$server->line_user_id)->first();
        $message.= '花名:'.$server->name."\n".'line名稱:'.$line_user->latest_name."\n";
      }
      $message .= $PartnerGroupPair->partner_id.':'.$PartnerGroupPair->group_type."\n";
    }
		return $message;
	}
	protected function SessionFunction( $args=null ) : string {
		
	}
}
