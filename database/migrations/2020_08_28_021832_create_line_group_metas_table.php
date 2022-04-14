<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLineGroupMetasTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('line_group_metas', function (Blueprint $table) {
			$table->id();
			$table->char('line_group_id',33);
			$table->char('name',64);
			$table->string('value',1200);
			$table->timestamps();
			
			$table->foreign('line_group_id')
				->references('id')
				->on('line_groups')
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
		Schema::dropIfExists('line_group_metas');
	}
}
