<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLineEventsTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('line_events', function (Blueprint $table) {
			$table->id();
			$table->timestamp('recieve_time')->useCurrent();
			$table->char('line_user_id',33);
			$table->char('line_group_id',33)->nullable();
			$table->text('event');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('line_events');
	}
}
