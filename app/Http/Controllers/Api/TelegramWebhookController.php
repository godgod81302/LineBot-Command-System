<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Telegram\tgApiHelper;

class TelegramWebhookController extends Controller
{
    private $helper;
    
    public function __construct(){
      $this->helper = tgApiHelper::helper( env('TELEGRAM_BOT_TOKEN') );
    }
    public function handler(Request $request){
      $log = Log::channel('tg');
      $hearer = getallheaders();
      $body = $request->getContent();
      $log->debug(['header'=>$hearer,'body'=>$body]);
      
      $update = json_decode($body, true);
      return $this->helper->push($update["message"]["chat"]["id"],$update["message"]["text"]);

    }

}
