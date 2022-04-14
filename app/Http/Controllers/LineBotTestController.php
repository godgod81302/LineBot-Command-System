<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LineBotTestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $content = file_get_contents('php://input');
        $json = json_decode($content, true);
        // $ChannelSecret = '46e60a0d3df81b4a356fedc00dd8109c'; 
        // $ChannelAccessToken = 'iwwuGMrL8iD6f2/+XiiBlL5Yo4ShUmuOXM7rc02RPQonVGiUXyT67WPtlvg+OGB0u6I+9Xbbo8qAEwqxX5BOrREMZLBdaCoHvumjxIwTMcgsd3y/77K4fTRXVKCanRdwPJHo2n4DVPoBtXdVSw1uhQdB04t89/1O/w1cDnyilFU='; 
        
        // //讀取資訊 
        // $HttpRequestBody = file_get_contents('php://input'); 
        // $HeaderSignature = $_SERVER['HTTP_X_LINE_SIGNATURE']; 
        
        // //驗證來源是否是LINE官方伺服器 
        // // $Hash = hash_hmac('sha256', $HttpRequestBody, $ChannelSecret, true); 
        // // $HashSignature = base64_encode($Hash); 
        // // if($HashSignature != $HeaderSignature) 
        // // { 
        // //     die('hash error!'); 
        // // } 
        
        // //輸出 
        // file_put_contents('apextest.txt', $json); 
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
