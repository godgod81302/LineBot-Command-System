<?php

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;
use App\Model\Booking;
use App\Model\Server;
use App\Line\ApiHelper;
class CustomArrive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'InformCustom';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if customer arrive.Send check message before service start.';

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
    {   $booking = new Booking;
        $msg = '';
        $next_pending_bookings = $booking->where('start_time', date('Y-m-d H:i',strtotime("+10 minute")).':00')
        ->where('status','Pending')->get();
        foreach(  $next_pending_bookings as $next_pending_booking  ){
          $server = Server::where('id',$next_pending_booking->server_id)->first();
          $msg = $next_pending_booking->sales_id.'預約'.date('Hi',strtotime($next_pending_booking->start_time)).$server->name.' 請問客到附近?';
          $helper = ApiHelper::helper(env('LINE_BOT_CHANNEL_ACCESS_TOKEN'));
          $messages = [
              [	'type' => 'text',	'text' =>  $msg ],
          ];
          $result = $helper->push($next_pending_booking->booking_group_id, $messages, true);
        }

    }
    
}
