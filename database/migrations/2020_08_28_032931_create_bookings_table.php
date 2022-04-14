<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBookingsTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('bookings', function (Blueprint $table) {
			$table->id();
			$table->dateTime('start_time')->comment('預定開始時機間');
			$table->dateTime('end_time')->comment('預定結束時間');
			$table->dateTime('real_start_time')->comment('實際開始時間')->nullable();
			$table->dateTime('real_end_time')->comment('實際結束時間')->nullable();
			$table->unsignedBigInteger('server_id')->index();
			$table->unsignedBigInteger('sales_id')->index()->nullable(); // 允許沒有業務的訂單	
			$table->char('admin_nickname',6)->comment('代訂管理員暱稱')->nullable();
			$table->char('booking_group_id',33);
			$table->char('custom_arrived_group_id',33)->nullable();
			$table->foreign('booking_group_id')
			->references('id')
			->on('line_groups')
			->onDelete('cascade')->comment('下定之群組');
			$table->integer('server_fee')->comment('服務員費用')->default(0);
			$table->integer('broker_fee')->comment('經紀人費用')->default(0);
			$table->integer('company_cost')->comment('店家成本')->default(0);
			$table->integer('company_profit')->comment('店家毛利')->default(0);
			$table->integer('marketing_cost')->comment('銷售成本')->default(0);
			$table->integer('sales_profit')->comment('業務毛利')->default(0);
			$table->smallInteger('period')->comment('服務時間長')->default(0);
			$table->enum('s_time', ['1','2','3','4','5','6','n'])->default(1);
			$table->integer('total_price')->comment('總收費額');
			$table->enum('is_pre_booking', ['Y','N'])->default('N');
			$table->enum('status',['Pending','Arrived','Aboard','Ready','Cancel','Close','Rest'])->default('Pending');
			$table->string('note',1200)->comment('特殊服務備註')->nullable();
			$table->char('remark',33)->comment('訂單其他備註')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('bookings');
	}
}
