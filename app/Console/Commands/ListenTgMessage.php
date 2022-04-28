<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use App\Model\CammandStatus;
class ListenTgMessage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ListenTelegram {option=default}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $option = $this->argument('option');

        switch ($option) {
            case 'start':
                $this->start();
                break;
            case 'stop':
                $this->stop();
                break;
            case 'default':
                echo 'php artisan ListenTelegram start | stop';
                exit;
       }

        return 0;
    }

    private function stop(){
        $command_status = CammandStatus::where('name','ListenTgMessage')->first();
        if ( $command_status ){
            $command_status->status = 'stop';
            $command_status->save();
        }
        else{
            $this->info("目前查無此命令曾執行過");
        }
    }

    private function start(){
        $command_status = CammandStatus::updateOrCreate(
            ['name'=>'ListenTgMessage'],
            ['status'=>'run']
        );



        
    }




}
