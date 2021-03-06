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

class AutoSchedule extends BaseCommand{
	
	private static $instance;
	
	public static function getInstance(){
		if( !self::$instance ){
			self::$instance = new AutoSchedule();
		}
		return self::$instance;
	}
	
	private function __construct(){
		$this->command_data = [
			'pre_command' => '#',
			'name' => '打卡',
			'cmd' => '打卡',
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
		$calendar_search_time = date("Y-m-d");
		if( date("H")<1 ){
			$calendar_search_time = date("Y-m-d",strtotime('-1 day'));
		}
		$calendar_datas = Calendar::where('date',$calendar_search_time)->first();
		if ( !$calendar_datas ){
				$ch = curl_init();
				curl_setopt($ch , CURLOPT_URL , "https://notify-api.line.me/api/notify");
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, ["message"=>'今天沒有被設定在班表，打卡失敗',]);
				$result = curl_exec($ch);
				curl_close($ch);
				exit;
		}
		$schedule_type = $calendar_datas->work_type;
		//怕耍白癡打成小寫 強轉大寫
		$schedule_type = strtoupper($schedule_type);		

		if( ($schedule_type != 'B') && ($schedule_type != 'C') && ($schedule_type != 'J') ){
			$ch = curl_init();
			curl_setopt($ch , CURLOPT_URL , "https://notify-api.line.me/api/notify");
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, ["message"=>'目前班别非预设班别 请赶紧重新设定',]);
			$result = curl_exec($ch);
			curl_close($ch);
			exit;
		}

		if ( strtoupper($schedule_type) == 'B' ){
			$morning_start_time = date("Y-m-d 08:50:00");
			$morning_end_time= date("Y-m-d 08:59:30");
			$evening_start_time= date("Y-m-d 18:00:00");
			$evening_end_time= date("Y-m-d 18:10:00");
		}
		else if( strtoupper($schedule_type) == 'C' ){
				$morning_start_time = date("Y-m-d 11:50:00");
				$morning_end_time= date("Y-m-d 11:59:30");
				$evening_start_time= date("Y-m-d 21:00:00");
				$evening_end_time= date("Y-m-d 21:10:00");
		}
		else if( strtoupper($schedule_type) == 'J' ){
				$morning_start_time = date("Y-m-d 14:50:00");
				$morning_end_time= date("Y-m-d 14:59:30");
				$evening_start_time= date("Y-m-d 00:00:00");
				$evening_end_time= date("Y-m-d 00:10:00");
		}

		$now_timestamp = strtotime(date("Y-m-d H:i:s"));
		$morning_start_timestamp = strtotime($morning_start_time);
		$morning_end_timestamp = strtotime($morning_end_time);
		$evening_start_timestamp = strtotime($evening_start_time);// insert your daily schedule start time
		$evening_end_timestamp = strtotime($evening_end_time);// insert your daily schedule end time
		$is_morning = false;
		$is_evening = false;
		if ( ($now_timestamp>$morning_start_timestamp) && ($now_timestamp<$morning_end_timestamp) ){
				$is_morning =true;
				$flag_data = Flag::whereBetween('time',[$morning_start_time,$morning_end_time])->first();
		}
		else if ( ($now_timestamp>$evening_start_timestamp) && ($now_timestamp<$evening_end_timestamp) ){
				$is_evening = true;
				$flag_data = Flag::whereBetween('time',[$evening_start_time,$evening_end_time])->first();
		}
		else{
				// echo 4;
				// $ch = curl_init();
				// curl_setopt($ch , CURLOPT_URL , "https://notify-api.line.me/api/notify");
				// curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				// curl_setopt($ch, CURLOPT_POST, true);
				// curl_setopt($ch, CURLOPT_POSTFIELDS, ["message"=>'非打卡正式時段 打卡中斷',]);
				// $result = curl_exec($ch);
				// curl_close($ch);
				exit;
		}
		
		if ($flag_data){
			$ch = curl_init();
			curl_setopt($ch , CURLOPT_URL , "https://notify-api.line.me/api/notify");
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, ["message"=>'今天已打過卡',]);
			$result = curl_exec($ch);
			curl_close($ch);
			exit;
	}


	if( ($now_timestamp<$morning_start_timestamp) || ($now_timestamp>$evening_end_timestamp) ){
    exit;
}
if ( (($now_timestamp>$morning_start_timestamp) && ($now_timestamp<$morning_end_timestamp)) ||  (($now_timestamp>$evening_start_timestamp) && ($now_timestamp<$evening_end_timestamp)) ){

    // This is where Selenium server 2/3 listens by default. For Selenium 4, Chromedriver or Geckodriver, use http://localhost:4444/
    $host = 'http://localhost:4444/wd/hub';

    $capabilities = DesiredCapabilities::chrome();

    $driver = RemoteWebDriver::create($host, $capabilities,5000);

    // navigate to Selenium page on Wikipedia
    $driver->get('https://applv1.ecmaker-cloud.com/globalgroup.html');

    // write 'PHP' in the search box
    $driver->findElement(WebDriverBy::id('id')) // find search input element
        ->sendKeys('20211203'); // fill the search box
    // submit the whole form
    $driver->findElement(WebDriverBy::id('pw'))
    ->sendKeys('E124819640')
    ->submit();

    $driver->findElement(
        WebDriverBy::cssSelector("div[data-key='1']")
    )->click();

    $driver->navigate()->refresh();
    if ( $is_morning ){
        $incard_time = $driver->findElement(WebDriverBy::id('incard'))->findElement(
            WebDriverBy::cssSelector("div")
        )->getText();
    }
    if ( $is_evening ){
        $incard_time = $driver->findElement(WebDriverBy::id('outcard'))->findElement(
            WebDriverBy::cssSelector("div")
        )->getText();
    }
    

    $message = '';
    if ( $is_morning ){
        $message = '上班';
    }
    if ( $is_evening ){
        $message = '下班';
    }
    if( $incard_time == '' ){
        $message .= '打卡失败';
    }
    else{
        $message .= $incard_time.'打卡成功';
    }
    $messages = array(
        'message' => $message
    );

    $ch = curl_init();
    curl_setopt($ch , CURLOPT_URL , "https://notify-api.line.me/api/notify");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $messages);
    $result = curl_exec($ch);
    curl_close($ch);
		DB::table('flags')->insert([
			'date' => $calendar_search_time,
			'time' => date("Y-m-d H:i:s"),
		]);
    // $result2 = mysqli_query($link,"INSERT INTO flag (date,time)VALUES('".date("Y-m-d")."','".date("Y-m-d H:i:s")."')");
    // wait until the target page is loaded
    $driver->wait(5,1000);
    sleep(5);
    $driver->close();
		$driver->quit();
    }
    else{
        echo 'error';
    }
		return $message;
	}
	protected function SessionFunction( $args=null ) : string {
		
	}
}
