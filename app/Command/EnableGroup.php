<?php
namespace App\Command;

use App\Model\LineGroup;

class EnableGroup extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new EnableGroup();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '&',
			'name' => '授權群組可以使用系統功能',
			'cmd' => '啟用群組',
			'description' => '授權群組可以使用系統功能',
			'access' => ['admin','group_admin'],
			'ignore_Acess_check'=>true,
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$user = $args->user;
		$group = $args->group;
    $command = $args->command;
    
		//過濾指令字
		$command_msg = substr($command, strlen($this->command_data['cmd']));    

    if ( !empty($command_msg) ){
      if ( $command_msg!='上' && $command_msg!='下' ){
        return '啟用群組後面請接上或下，例如:&啟用群組上';
      }
      if ( $command_msg == '下' ){
        $line_group = LineGroup::where('id',$group->id)->update(['enable'=>'N']);
        if ( !$line_group ){
          return '錯誤，禁用失敗，請聯繫工程師';
        }
        else{
          return '群組禁用成功';
        }
      }
    }
    if( $group->enble=='Y' ){
			$message = "群組已啟用，無須再次啟用";
			return $message;
		}
    $line_group = LineGroup::where('id',$group->id)->update(['enable'=>'Y']);
    if ( !$line_group ){
      return '錯誤，啟用失敗，請聯繫工程師';
    }
    else{
      return '群組啟用成功';
    }

	}
	protected function SessionFunction( $args=null ) : string {
		
	}
}
