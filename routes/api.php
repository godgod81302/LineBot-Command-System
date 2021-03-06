<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::namespace('Api')->prefix('v1')->group(function(){
	Route::post('/webhook','LineWebhookController@handler')->name('webhook.handler');
    Route::get('/loadschedule','ScheduleController@loadSchedule');
    Route::post('/postdata','ScheduleController@postData');
    Route::post('/tgwebhook','TelegramWebhookController@handler')->name('tgwebhook.handler');
});


Route::middleware('auth:api')->get('/user', function (Request $request) {
	return $request->user();
});

Route::post('/LineBotWEBHOOK', function()
{
    echo 123;exit;
    //設定Token 
    $ChannelSecret = '46e60a0d3df81b4a356fedc00dd8109c'; 
    $ChannelAccessToken = 'iwwuGMrL8iD6f2/+XiiBlL5Yo4ShUmuOXM7rc02RPQonVGiUXyT67WPtlvg+OGB0u6I+9Xbbo8qAEwqxX5BOrREMZLBdaCoHvumjxIwTMcgsd3y/77K4fTRXVKCanRdwPJHo2n4DVPoBtXdVSw1uhQdB04t89/1O/w1cDnyilFU='; 

    //讀取資訊 
    $HttpRequestBody = file_get_contents('php://input'); 
    $HeaderSignature = $_SERVER['HTTP_X_LINE_SIGNATURE']; 

    //驗證來源是否是LINE官方伺服器 
    $Hash = hash_hmac('sha256', $HttpRequestBody, $ChannelSecret, true); 
    $HashSignature = base64_encode($Hash); 
    if($HashSignature != $HeaderSignature) 
    { 
    die('hash error!'); 
    } 

    //輸出 
    file_put_contents('testlog.txt',  $HttpRequestBody); 
});