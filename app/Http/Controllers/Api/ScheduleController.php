<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;
use App\Model\Calendar;

class ScheduleController extends Controller
{
    public function loadSchedule(){
        $last_month_first_date = date("Y-m-1",strtotime('last month'));
        $next_month_last_date = date('Y-m-d',strtotime(date('Y-m-1',strtotime('next month')).'+1 month -1 day'));
        $flag_data = Calendar::whereBetween('date',[$last_month_first_date,$next_month_last_date])->get();
        return json_encode($flag_data);
    }
    public function postData(Request $request){
        $result = true;
        if ( $request->schedule_type=='N' ){
            $sql_result = Calendar::whereIn('date',$request->dates)->delete();
            if ( !$sql_result ){
                $result = false;
            }
        }
        else{
            foreach( $request->dates as $date ){
                $sql_result = Calendar::updateOrCreate(['date'=>$date],['work_type'=>$request->schedule_type,'month'=>date("m",strtotime($date))]);
                if ( !$sql_result ){
                    $result = false;
                }
            }
        }
        return $result;
    }
}
