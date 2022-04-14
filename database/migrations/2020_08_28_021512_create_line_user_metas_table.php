<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLineUserMetasTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('line_user_metas', function (Blueprint $table) {
			$table->id();
			$table->char('line_user_id',33);
			$table->char('name',64);
			$table->string('value',1200);
			$table->timestamps();
			
			$table->foreign('line_user_id')
				->references('id')
				->on('line_users')
				->onDelete('cascade');
			$table->index('name');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('line_user_metas');
	}
}
