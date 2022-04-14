<?php

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;
use App\Model\Server;
use App\Model\ScheduleChange;
use App\Model\ScheduleUnit;
class ServerScheduleGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ServerScheduleGenerate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
      //如果預定時間都沒了，說明這服務員根本就已經不服務了
      $servers = Server::where('duty_start_time','<>','')->where('duty_end_time','<>','')->whereNotNull('line_user_id')->where('enable','<>','N')->get();
      foreach ( $servers as $server_data ){
        $start_time = date('Y-m-d ').$server_data->duty_start_time;
        $end_time = date('Y-m-d ').$server_data->duty_end_time;
        if (substr($server_data->duty_start_time,0,2)<config('app.system.day_split_hour')){
          $start_time = date('Y-m-d ',strtotime("+1 day")).$server_data->duty_start_time;
        }
        if (substr($server_data->duty_end_time,0,2)<config('app.system.day_split_hour')){
          $end_time = date('Y-m-d ',strtotime("+1 day")).$server_data->duty_end_time;
        }
        $schedule_change = ScheduleChange::where('server_id',$server_data->id)->where('start_time','>=',date('Y-m-d 07:00:00'))->where('end_time','<=',date("Y-m-d 07:00:00",strtotime("+1 day")))->first();
        if ($schedule_change){
          $start_time = $schedule_change->start_time;
          $end_time = $schedule_change->end_time;
        }

        $server = Server::where('id',$server_data->id)->first();
        $server->start_time = $start_time;
        $server->end_time = $end_time;
        $result = $server->save();
        if ($result){
          if ($schedule_change){
            $schedule_change->delete();
          }
        }
        $schedule_begin_time=strtotime(date('Y-m-d 07:00:00'));
        $schedule_end_time=$schedule_begin_time+86400*2-300;
        while($schedule_end_time>$schedule_begin_time){
          $result = ScheduleUnit::updateOrCreate(
            ['start_time'=>date('Y-m-d H:i:s',$schedule_begin_time),'server_id'=>$server_data->id],
            ['end_time'=>date('Y-m-d H:i:s',$schedule_begin_time+300)]
          );
          $schedule_begin_time += 300;
        }
      }

    }
}
