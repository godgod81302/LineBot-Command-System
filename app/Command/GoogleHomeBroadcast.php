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
    $message = '';
    if ( !empty($command) ){
      //https://www.voicerss.org/ 使用這個語音tts服務
      // $google_tts_url = "https://translate.google.com/translate_tts?ie=UTF-8&total=1&idx=0&client=tw-ob&q=".$command."&tl=zh-cn";
      $google_tts_url = "https://api.voicerss.org/?key=9afcdd1a0e164e539f26b2c285a9282c&hl=zh-tw&c=MP3&src=".$command;
      $tts_data = $this->down_mp3($google_tts_url);
      $result = exec("py test.py");
      return $command;
    }
    else{
      return '廣播內容為空';
    }

	}
	protected function SessionFunction( $args=null ) : string {
		
	}
  private function down_mp3($url){
    $fileName = "test.mp3";
    header ( "Content-Type:audio/mpeg");
    header ( "accept-encoding: gzip, deflate, br");
    header ( "accept-language: zh-TW,zh;q=0.9,en-US;q=0.8,en;q=0.7");
    header ( "cache-control: max-age=0");
    $file = file_get_contents($url);
    $fp = fopen($fileName, 'w');
    fwrite($fp, $file);
    fclose($fp);
  }
}
