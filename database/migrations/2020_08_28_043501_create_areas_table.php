<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAreasTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('areas', function (Blueprint $table) {
			$table->id();
			$table->timestamps();
			$table->string('name',256);
			$table->char('meeting_point',50)->comment('約客地地址');
			$table->string('meeting_point_photos',1200)->comment('約客地照片')->nullable();
			$table->char('gps_position',15)->comment('據點gps位置')->nullable();
		});
		
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('areas');
	}
}
