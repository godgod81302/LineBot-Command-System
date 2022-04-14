<?php

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;
use App\Model\Booking;
use App\Model\ScheduleUnit;
class DeleteOldData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DeleteOldData';

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
        
        $is_update_schedule_sucess = ScheduleUnit::
        where('start_time','<',date('Y-m-d',strtotime("now")-86400*3))
        ->delete();
        $is_update_booking_sucess = Booking::
        where('start_time','<',date('Y-m-d',strtotime("now")-86400*14))
        ->delete();

    }
}
