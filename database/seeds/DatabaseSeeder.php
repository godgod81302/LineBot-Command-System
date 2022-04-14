<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
	/**
	 * Seed the application's database.
	 *
	 * @return void
	 */
	public function run()
	{
		$this->line_users();
		$this->line_group();
		$this->partners();
		$this->brokers();
		$this->areas();
		$this->contries();
		$this->sales();
		$this->servers();
		$this->group_admins();
		$this->partner_sales_auth();
		$this->services();
		$this->schedule_unit();
		//目前用不到booking測資
		// $this->bookings();
		
	}
	
	private function line_users(){
		DB::table('line_users')->insert([
			[
				'id' => 'U46bbf939965ddc804b9bcc80321ad0c2',
				'latest_name'=>'三億帥哥Ben',
				'latest_img_url'=>'',
			], // LINE ID: operator981
			[
				'id' => 'Uff94af9b4155c1c01fe9fb9be8c74fcd',
				'latest_name'=>'耀金服務',
				'latest_img_url'=>'',
			], // LINE ID: yaojin_service
			[
				'id' => 'U3ae0425e59bfafa9bf085eddb24069a9',
				'latest_name'=>'Apex',
				'latest_img_url'=>'',
			], // LINE ID: pua-style
		]);		
	}
	
	private function line_group(){
		DB::table('line_groups')->insert([
			[
				'id' => 'Cf662295ca3f3527dff875e7c62dc895e',
				'name' => '測試2',
				'enable' => 'Y',
			],
			[
				'id' => 'Cc28355a1f95a7dccdae2a9ab1a9a4676',
				'name' => 'apex測試群組',
				'enable' => 'Y',
			],
		]);
	}
	
	private function partners(){
		DB::table('partners')->insert([
			['name'=>'管理系統'],
			['name'=>'腥巴克'],
			['name'=>'大帥哥'],
		]);
	}
	
	private function brokers(){
		DB::table('brokers')->insert([
			[
				'line_user_id'=>'U46bbf939965ddc804b9bcc80321ad0c2',
				'sn'=>'B'.date('ymds').explode('.',number_format(microtime(true),3,'.',''))[1],
			],
			[
				'line_user_id'=>'Uff94af9b4155c1c01fe9fb9be8c74fcd',
				'sn'=>'B'.date('ymds').explode('.',number_format(microtime(true),3,'.',''))[1],
			],
		]);
	}
	
	private function areas(){
		DB::table('areas')->insert([
			['name'=>'測試區'],
		]);
	}
	
	private function contries(){
		DB::table('countries')->insert([
			['created_at'=>date('Y-m-d H:i:s'), 'name'=>'台灣', 'text_mark'=>''],
		]);
	}
	
	private function sales(){
		DB::table('sales')->insert([
			[
				'line_user_id'=>'U46bbf939965ddc804b9bcc80321ad0c2',
				'sn'=>'S'.date('ymds').explode('.',number_format(microtime(true),3,'.',''))[1],
			],
		]);
		DB::table('sales')->insert([
			[
				'line_user_id'=>'U3ae0425e59bfafa9bf085eddb24069a9',
				'sn'=>'S'.date('ymds').explode('.',number_format(microtime(true),3,'.',''))[1],
			],
		]);
	}
	
	private function servers(){
		DB::table('servers')->insert([
			[
				'created_at' => date('Y-m-d H:i:s'),
				'partner_id'=>2,
				'broker_id'=>1,
				'country_id'=>1,
				'name'=>'Test花花',
				'area_id'=>1,
			],
			[
				'created_at' => date('Y-m-d H:i:s'),
				'partner_id'=>2,
				'broker_id'=>1,
				'country_id'=>1,
				'name'=>'Test泡泡',
				'area_id'=>1,
			],
			[
				'created_at' => date('Y-m-d H:i:s'),
				'partner_id'=>2,
				'broker_id'=>2,
				'country_id'=>1,
				'name'=>'Test毛毛',
				'area_id'=>1,
			],
			[
				'created_at' => date('Y-m-d H:i:s'),
				'partner_id'=>3,
				'broker_id'=>1,
				'country_id'=>1,
				'name'=>'Test丁丁',
				'area_id'=>1,
			],
			[
				'created_at' => date('Y-m-d H:i:s'),
				'partner_id'=>3,
				'broker_id'=>2,
				'country_id'=>1,
				'name'=>'Test拉拉',
				'area_id'=>1,
			],
			[
				'created_at' => date('Y-m-d H:i:s'),
				'partner_id'=>3,
				'broker_id'=>2,
				'country_id'=>1,
				'name'=>'Test迪西',
				'area_id'=>1,
			],
		]);
	}
	
	private function group_admins(){
		
		DB::table('group_admins')->insert([
			['line_user_id'=>'U46bbf939965ddc804b9bcc80321ad0c2', 'partner_id'=>1,'nickname'=>'班'],
			['line_user_id'=>'U3ae0425e59bfafa9bf085eddb24069a9', 'partner_id'=>2,'nickname'=>'華'],
		]);

	}
	private function schedule_unit(){
		
		$BeginTime=date('Y-m-d');
		$BeginTime=strtotime($BeginTime);
		$EndTime=$BeginTime+604800;
		for ( $i=1;$i<4;$i++ ){

			DB::table('servers')
			->where('id', $i)
			->update(['start_time' => date('Y-m-d H:i:s',$BeginTime),'end_time' =>date('Y-m-d H:i:s',$EndTime)]);

			while($EndTime>$BeginTime){
				DB::table('schedule_units')->insert([
					[
						'created_at'=>date('Y-m-d H:i:s'),
						'start_time'=>date('Y-m-d H:i:s',$BeginTime),
						'end_time'=>date('Y-m-d H:i:s',$BeginTime+300),
						'server_id'=>$i,
					],
				]);
				$BeginTime=$BeginTime+300;
			}
			
		}


	}
	private function partner_sales_auth(){
		DB::table('partner_sales_auth')->insert([
			[
				'create_at' => date('Y-m-d H:i:s'),
				'partner_id' => 2,
				'sales_id' => 1,
			],
			[
				'create_at' => date('Y-m-d H:i:s'),
				'partner_id' => 2,
				'sales_id' => 2,
			],
		]);
	}
	
	private function services(){
		for($i=1; $i<=6; $i++){
			DB::table('services')->insert([
				[
					'created_at' => date('Y-m-d H:i:s'),
					'server_id' => $i,
					'name' => 'short_service',
					'description' => '短時服務',
					'server_fee' => 1200+$i*100,
					'broker_fee' => 200,
					'company_cost' => 0,
					'company_profit' => 300,
					'marketing_cost' => 0,
					'sales_profit' => 0,
					'period' => 30,
				],
				[
					'created_at' => date('Y-m-d H:i:s'),
					'server_id' => $i,
					'name' => 'long_service',
					'description' => '長時服務',
					'server_fee' => 2000+$i*100,
					'broker_fee' => 200,
					'company_cost' => 0,
					'company_profit' => 300,
					'marketing_cost' => 0,
					'sales_profit' => 0,
					'period' => 50,
				],
			]);
		}
	}
	
	private function bookings(){
		$time = time() - 26*60;
		DB::table('bookings')->insert([
			[
				'start_time' => date('Y-m-d H:i:s',$time),
				'end_time' => date('Y-m-d H:i:s',$time+30*60),
				'server_id' => 1,
				'sales_id' => 1,
				'server_fee' => 1300,
				'broker_fee' => 200,
				'company_cost' => 0,
				'company_profit' => 300,
				'marketing_cost' => 0,
				'sales_profit' => 0,
				'note' => ''
			],
			[
				'start_time' => date('Y-m-d H:i:s',($time+30*60)+40*60),
				'end_time' => date('Y-m-d H:i:s',($time+30*60)+60*60),
				'server_id' => 1,
				'sales_id' => 1,
				'server_fee' => 1300,
				'broker_fee' => 200,
				'company_cost' => 0,
				'company_profit' => 300,
				'marketing_cost' => 0,
				'sales_profit' => 0,
				'note' => ''
			],
		]);
	}
}
