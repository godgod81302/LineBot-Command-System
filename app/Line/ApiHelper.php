<?php
namespace App\Line;

use Illuminate\Support\Facades\Log;

class ApiHelper{
	
	private static $instance;
	private $channel_access_token;
	public $statusCode = null;
	public $response = null;
	
	public static function helper( $channel_access_token ){
		if( !self::$instance )
			self::$instance = new ApiHelper($channel_access_token);
		return self::$instance;
	}
	private function __construct( $channel_access_token ){
		$this->channel_access_token = $channel_access_token;
	}
	
	private function callGet( $url ){
		$curl = curl_init();
		curl_setopt_array( $curl,[
			CURLOPT_URL => $url,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
				'Authorization: Bearer '.$this->channel_access_token,
			],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER=> false,
		]);
		$this->response = curl_exec($curl);
		$this->statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if( $this->statusCode!=200 ){
			Log::channel('line-api')->error('code: '.$this->statusCode.'; response: '.$this->response.'; url: '.$url);
		}
		return $this->response;
	}
	
	private function callPost( $url, $data ){
		$curl = curl_init();
		curl_setopt_array( $curl,[
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json; charset=utf-8',
				'Authorization: Bearer '.$this->channel_access_token,
				//'Content-Length: '.strlen($data),
			],
			CURLOPT_POSTFIELDS => $data,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER=> false,
		]);
		$this->response = curl_exec($curl);
		$this->statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		if( $this->statusCode!=200 ){
			Log::channel('line-api')->error('code: '.$this->statusCode.'; response: '.$this->response.'; url: '.$url.'; data: '.$data);
		}
		return $this->response;
	}
	
	public function reply( $reply_token, $messages, $notification_disabled=false ){
		$url = 'https://api.line.me/v2/bot/message/reply';
		$data = json_encode(['replyToken'=>$reply_token, 'messages'=>$messages, 'notificationDisabled'=>$notification_disabled]);
		if( config('app.debug') ) echo 'reply messages: '.print_r($messages,true);
		return $this->callPost( $url, $data );
	}
	
	public function push( $to, $messages, $notification_disabled=false ){
		$url = 'https://api.line.me/v2/bot/message/push';
		$data = json_encode(['to'=>$to, 'messages'=>$messages, 'notificationDisabled'=>$notification_disabled]);
		if( config('app.debug') ) echo 'push messages: '.print_r($messages,true);
		return $this->callPost( $url, $data );
	}
	
	public function multicast( $to, $messages, $notification_disabled=false ){
		$url = 'https://api.line.me/v2/bot/message/multicast';
		$data = json_encode(['to'=>$to, 'messages'=>$messages, 'notificationDisabled'=>$notification_disabled]);
		if( config('app.debug') ) echo 'multicast messages: '.print_r($messages,true);
		return $this->callPost( $url, $data );
	}
	
	public function getContent( $message_id ){
		$url = "https://api-data.line.me/v2/bot/message/{$message_id}/content";
		return $this->callGet( $url );
	}
	
	public function getSendMessagesLimit(){
		$url = 'https://api.line.me/v2/bot/message/quota';
		$data_string = $this->callGet( $url );
		$data = json_decode($data_string);
		if( $data->type=='limited' );
			return $data->value;
		
		return 0;
	}
	
	public function getMessageSendThisMonth(){
		$url = 'https://api.line.me/v2/bot/message/quota/consumption';
		$data_string = $this->callGet( $url );
		$data = json_decode($data_string);
		return $data->totalUsage;		
	}
	
	private function getMessageNumber( $url, $data ){
		$format_date = date('Ymd',strtotime($data));
		$url = 'https://api.line.me/v2/bot/message/delivery/reply?date='.$format_date;
		$data_string = $this->callGet( $url );
		$data = json_decode( $data_string );
		if( $data->status=='ready' )
			return $data->success;
		if( $data->status=='unready' )
			return null;
		if( $data->status=='out_of_service' )
			return false;
	}
	
	public function getNumberReplyMessageSend( $date ){
		$format_date = date('Ymd',strtotime($date));
		$url = 'https://api.line.me/v2/bot/message/delivery/reply?date='.$format_date;
		$data_string = $this->callGet( $url );
		$data = json_decode( $data_string );
		print_r($data);
		if( $data->status=='ready' )
			return $data->success;
		if( $data->status=='unready' )
			return null;
		if( $data->status=='out_of_service' )
			return false;
	}
	
	public function getNumberPushMessageSend( $data ){
		$format_date = date('Ymd',strtotime($data));
		$url = 'https://api.line.me/v2/bot/message/delivery/push?date='.$format_date;
		$data_string = $this->callGet( $url );
		$data = json_decode( $data_string );
		if( $data->status=='ready' )
			return $data->success;
		if( $data->status=='unready' )
			return null;
		if( $data->status=='out_of_service' )
			return false;	
	}
	
	public function getUserProfile( $user_id ){
		$url = 'https://api.line.me/v2/bot/profile/'.$user_id;
		$data_string = $this->callGet( $url );
		$data = json_decode( $data_string );
		return $data;
	}
	
	public function getGroupSummary( $group_id ){
		$url = 'https://api.line.me/v2/bot/group/'.$group_id.'/summary';
		$data_string = $this->callGet( $url );

		$data = json_decode( $data_string );
		return $data;
  }
  
  public function getGroupMemberProfile( $group_id, $user_id ){
		$url = 'https://api.line.me/v2/bot/group/'.$group_id."/member/{$user_id}";
		$data_string = $this->callGet( $url );
		$data = json_decode( $data_string );
		return $data;
	}
	//只是寫著放，目前我們的帳號不是官方認證過，所以無權作這種事
	public function getGroupMemberIds( $group_id ){
		$url = 'https://api.line.me/v2/bot/group/'.$group_id."/members/ids";
		$data_string = $this->callGet( $url );
		$data = json_decode( $data_string );
		return $data;
  }
	public function getFollwerIds(){
		$url = 'https://api.line.me/v2/bot/followers/ids';
		$data_string = $this->callGet( $url );
		$data = json_decode( $data_string );
		return $data;
  }
	
}