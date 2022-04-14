<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Model\LineEvent;
use App\Line\EventHandler;
use App\Line\ApiHelper;

class LineWebhookController extends Controller
{
	public function handler(Request $request){
		$log = Log::channel('line');
		$channel_access_token = env('LINE_BOT_CHANNEL_ACCESS_TOKEN');
		$channel_secret = env('LINE_BOT_CHANNEL_SECRET');

		$signature = $request->header('X-LINE-SIGNATURE');
		$hearer = getallheaders();
		$body = $request->getContent();
		// file_put_contents('body.txt',$body);
		$log->debug(['header'=>$hearer,'body'=>$body]);
		if( !$signature ){
			$log_data = [
				'reason' => 'No Signature',
				'header' => getallheaders(),
				'body' => $body,
			];
			$log->notice($log_data);
			
			echo 'signature fail';
			return;
		}
		
		$log_data = [
			'body' => $body,
			'X-LINE-SIGNATURE' => $signature,
		];

		$hash = hash_hmac('sha256', $body, $channel_secret, true);
		$content_check = base64_encode($hash);

		if( $content_check!==$signature ){
			$reason = ['reason'=>'validate fail'];
			$log_data = $reason + $log_data;
			$log->notice($log_data);
			echo 'validate fail';
			return;
		}
		
		$data = json_decode($body);	
		if( !$data ){
			$log->error([
				'reason' => 'JSON parse fail',
				'body' => $body,
			]);
			echo 'JSON parse fail';
			return;
		}

		$handler = new EventHandler( $channel_access_token );

		foreach( $data->events as $index => $event ){
			$line_event = new LineEvent();

			// 非user傳送的message不做紀錄
			if( isset($event->source->userId) ){
				$line_event->line_user_id = $event->source->userId;
				$source_type = $event->source->type;
				if( $source_type=='group' )
					$line_event->line_group_id = $event->source->groupId;
				$line_event->event = json_encode($event);
				$line_event->save();

			}
			if (  $index == (count($data->events)-1) ){
				$event->is_final_event = true;
			}
			$handler->handle( $event );
		}

		// print_r($request_data);
		//return	\App\Model\LineRole::all();
	}
}
