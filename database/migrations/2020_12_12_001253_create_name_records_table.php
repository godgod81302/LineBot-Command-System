<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNameRecordsTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('name_records', function (Blueprint $table) {
			$table->id();
      $table->timestamp('detect_at');
      $table->string('name',80);
      $table->string('recordable_type',255);
      $table->char('recordable_id',33);
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('name_records');
	}
}
