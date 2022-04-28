<?php

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;
use App\Model\Booking;
use App\Model\Server;
use App\Line\ApiHelper;
class checkInWork extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkInWork';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'checkInWork';

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
      // $headers = array(
      //   'Content-Type: multipart/form-data',
      //   'Authorization: Bearer QfJtDAozUvpIFe0hISzqVHXd92z5zlcazmanOtoQQoO'
      // );
      // $ch = curl_init();
			// curl_setopt($ch , CURLOPT_URL , "https://notify-api.line.me/api/notify");
			// curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			// curl_setopt($ch, CURLOPT_POST, true);
			// curl_setopt($ch, CURLOPT_POSTFIELDS, ["message"=>'測試排程',]);
			// $result = curl_exec($ch);
			// curl_close($ch);
    }
    
}
