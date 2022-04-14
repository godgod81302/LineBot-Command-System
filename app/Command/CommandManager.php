<?php
namespace App\Command;

class CommandManager{
	const USUAL_PRE_COMMAND = '#';
	const CTRL_PRE_COMMAND = '&';
	private static $COMMANDS = [];
	
	public static function init(){
		
		self::$COMMANDS = [
			self::USUAL_PRE_COMMAND => [],
			self::CTRL_PRE_COMMAND => [],
		];
		
		$list = scandir( __DIR__ );
		// print_r($list);
		foreach( $list as $file ){
			if( $file==='.' || $file==='..' )
				continue;
			
			$file_path = __DIR__ . DIRECTORY_SEPARATOR . $file;
			if( is_file($file_path) ){
				
				if( preg_match( '/\.php$/', $file_path ) ){
					require_once $file_path;
				}
			}
		}
		$all_class = get_declared_classes();
		$commands = [];
		foreach( $all_class as $class ){
			if( strpos($class, __NAMESPACE__)===0 && strpos($class, 'BaseCommand')===false && strpos($class, 'CommandManager')===false ){
				
				if( !method_exists($class, 'getInstance') )
					continue;
					
				$instance = $class::getInstance();
				if( $instance instanceof \App\Command\BaseCommand ){
					$command_data = $instance->getCommandData();
					if( $command_data['pre_command']==self::USUAL_PRE_COMMAND )
						self::$COMMANDS[self::USUAL_PRE_COMMAND][$command_data['cmd']] = $instance;
					elseif( $command_data['pre_command']==self::CTRL_PRE_COMMAND )
						self::$COMMANDS[self::CTRL_PRE_COMMAND][$command_data['cmd']] = $instance;
				}
			}
		}
	}

	public static function getCommand( $command_msg ){
		$return_cmd = null;
		$default_cmd = null;
		foreach( self::$COMMANDS as $pre_command => $commands ){
			if( strpos($command_msg, $pre_command)===0){
				$command_msg = substr($command_msg, strlen($pre_command));
				
				foreach( $commands as $command ){
					$command_data = $command->getCommandData();
					if( strpos($command_msg, $command_data['cmd'])===0 ){
						$return_cmd = $command;
					}
					if( $pre_command==='#' && $command_data['cmd']=='誰' ){
						$default_cmd = $command;
					}
				}
				break;
			}
		}
		if( !$return_cmd )
			$return_cmd = $default_cmd;
		
		return $return_cmd;
	}
	
	public static function getAllCommand(){
		return self::$COMMANDS;
	}
}