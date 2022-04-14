<?php
namespace App\Command;

use App\Model\Server;

use Illuminate\Support\Facades\Redis;

class CammandGuide extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new CammandGuide();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '命令使用指令表',
			'cmd' => '指令表',
			'description' => '命令使用指令表',
      'access' => ['admin','group_admin'],
      'authorized_group_type' => ['Admin'],
		];
	}
		
	/* 實作 */
	protected function process( $args=null ) : string {
		$user = $args->user;
		$group = $args->group;
		$command = $args->command;
    $group = $args->group;
		if( !$group || $group->enble=='N' ){
			$message = "未授權";
			return $message;
		}

		//過濾指令字
		$command_msg = mb_substr($command, mb_strlen($this->command_data['cmd']));
    $command_class_array = [];
    $except_classes = ['App\Command\CommandManager','App\Command\Command','App\Command\BaseCommand','App\Command\CommandUtil'];
    foreach ( get_declared_classes() as $declared_classes ){
      if ( substr($declared_classes,0,11)=='App\Command' && !in_array($declared_classes,$except_classes)){
        array_push($command_class_array,$declared_classes);
      }
    }
    if (empty($command_msg)){
      $message = '命令列表:'."\n";
      foreach ( $command_class_array as $class_name ){
        $class = $class_name::getInstance();
        $message .= $class->command_data['cmd']."\n";
      }
    }
    else{
      $is_find_command  = false;
      foreach ( $command_class_array as $class_name ){
        $class = $class_name::getInstance();
        $message = '';
        if ( $command_msg == $class->command_data['cmd'] ){
          $is_find_command = true;
          $message = $class->command_data['cmd']."\n".$class->command_data['description'];
          break;
        }
      }
      if (!$is_find_command){
        $message = '未找到查詢之指令';
      }
    }

    return $message;
  }
  //尚未檢查業務群裡面的人員
	protected function SessionFunction( $args=null ) : string {

  }
  


}
