<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalesTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('sales', function (Blueprint $table) {
			$table->id();
			$table->char('line_user_id',33)->unique();
			$table->char('sn',12);
			$table->timestamps();
			
			$table->foreign('line_user_id')
				->references('id')
				->on('line_users')
				->onDelete('cascade');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('sales');
	}
}
