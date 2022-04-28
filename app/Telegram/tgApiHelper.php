<?php
namespace App\Telegram;

use Illuminate\Support\Facades\Log;

class tgApiHelper{
	
	private static $instance;
	private $TELEGRAM_BOT_TOKEN;
	public $statusCode = null;
	public $response = null;
  private $api_url;
	public static function helper( $TELEGRAM_BOT_TOKEN ){
		if( !self::$instance )
			self::$instance = new tgApiHelper($TELEGRAM_BOT_TOKEN);
		return self::$instance;
	}
	private function __construct( $TELEGRAM_BOT_TOKEN ){
		$this->TELEGRAM_BOT_TOKEN = $TELEGRAM_BOT_TOKEN;
    $this->api_url = 'https://api.telegram.org/bot'.$TELEGRAM_BOT_TOKEN."/";
	}
	
	private function callGet( $url ){
		$curl = curl_init();
		curl_setopt_array( $curl,[
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER=> false,
		]);
		$this->response = curl_exec($curl);
		$this->statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if( $this->statusCode!=200 ){
			Log::channel('tg-api')->error('code: '.$this->statusCode.'; response: '.$this->response.'; url: '.$url);
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
				'Authorization: Bearer '.$this->TELEGRAM_BOT_TOKEN,
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
			Log::channel('tg-api')->error('code: '.$this->statusCode.'; response: '.$this->response.'; url: '.$url.'; data: '.$data);
		}
		return $this->response;
	}
	
	public function reply( $reply_token, $messages, $notification_disabled=false ){
		$url = 'https://api.line.me/v2/bot/message/reply';
		$data = json_encode(['replyToken'=>$reply_token, 'messages'=>$messages, 'notificationDisabled'=>$notification_disabled]);
		if( config('app.debug') ) echo 'reply messages: '.print_r($messages,true);
		return $this->callPost( $url, $data );
	}
	
	public function push( $chatID, $messages ){
    $sendto =$this->api_url."sendmessage?chat_id=".$chatID."&text=".$messages;
		// if( config('app.debug') ) echo 'push messages: '.print_r($messages,true);

		return $this->callGet( $sendto );
	}

}