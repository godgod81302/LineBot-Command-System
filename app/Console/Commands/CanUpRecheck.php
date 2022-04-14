<?php

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;
use App\Line\ApiHelper;
use App\Model\Booking;

class CanUpRecheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CanUpRecheck';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Every Five Minutes Remind Server Can Up?';

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
        $booking = new Booking;
        $msg = '';
        $Arrived_bookings = $booking
        ->where(function ($query) {
            $query->where('start_time', date('Y-m-d H:i',strtotime("-5 minute")).':00');
            $query->orwhere('start_time', date('Y-m-d H:i',strtotime("-10 minute")).':00');
            $query->orwhere('start_time', date('Y-m-d H:i',strtotime("-15 minute")).':00');
        })
        ->where('status','Arrived')->get();
        
        foreach(  $Arrived_bookings as $Arrived_booking  ){
          $server = Server::where('id',$Arrived_booking->server_id)->first();

          $msg = $Arrived_booking->sales_id.'預約'.date('Hi',strtotime($Arrived_booking->start_time)).$server->name.' 請問客到附近?';

          $helper = ApiHelper::helper(env('LINE_BOT_CHANNEL_ACCESS_TOKEN'));
          $messages = [
              [	'type' => 'text',	'text' =>  $msg ],
          ];
          $result = $helper->push($server->line_group_id, $messages, true);
        }

        

    }
}
