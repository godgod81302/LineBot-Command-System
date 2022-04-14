<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScheduleUnitsTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('schedule_units', function (Blueprint $table) {
			$table->id();
			$table->timestamps();
			$table->dateTime('start_time')->comment('開始時間');
			$table->dateTime('end_time')->comment('結束時間');
			$table->foreignId('server_id')->constrained()->onDelete('cascade');
			$table->foreignId('booking_id')->nullable()->constrained()->onDelete('set null');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('schedule_units');
	}
}
