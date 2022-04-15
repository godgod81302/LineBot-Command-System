<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/set', function () {
    return view('set');
});

Route::get('ServerImg/{file_name}', function($file_name = null)
{
    $content = Storage::disk('Server_images')->get($file_name);
    if ($content) {
        return Response::make($content, 200, array('Content-Type' => 'image'));
    }
});
Route::get('RoomImg/{file_name}', function($file_name = null)
{
    $content = Storage::disk('Room_images')->get($file_name);
    if ($content) {
        return Response::make($content, 200, array('Content-Type' => 'image'));
    }
});
Route::get('ServicePointImg/{file_name}', function($file_name = null)
{
    $content = Storage::disk('Service_Point_images')->get($file_name);
    if ($content) {
        return Response::make($content, 200, array('Content-Type' => 'image'));
    }
});
// Route::post('/LineBotWEBHOOK', 'LineBotTestController@index')->name('line.webhook');
// Route::post('/LineBotWEBHOOK', function()
// {   echo 'yo';
//     Log::info('test');
    
//     // //設定Token 
//     // $ChannelSecret = '46e60a0d3df81b4a356fedc00dd8109c'; 
//     // $ChannelAccessToken = 'iwwuGMrL8iD6f2/+XiiBlL5Yo4ShUmuOXM7rc02RPQonVGiUXyT67WPtlvg+OGB0u6I+9Xbbo8qAEwqxX5BOrREMZLBdaCoHvumjxIwTMcgsd3y/77K4fTRXVKCanRdwPJHo2n4DVPoBtXdVSw1uhQdB04t89/1O/w1cDnyilFU='; 

//     // //讀取資訊 
//     // $HttpRequestBody = file_get_contents('php://input'); 
//     // $HeaderSignature = $_SERVER['HTTP_X_LINE_SIGNATURE']; 

//     // //驗證來源是否是LINE官方伺服器 
//     // $Hash = hash_hmac('sha256', $HttpRequestBody, $ChannelSecret, true); 
//     // $HashSignature = base64_encode($Hash); 
//     // if($HashSignature != $HeaderSignature) 
//     // { 
//     // die('hash error!'); 
//     // } 

//     // //輸出 
//     // file_put_contents('testlog.txt', '123456789qq'); 
// });