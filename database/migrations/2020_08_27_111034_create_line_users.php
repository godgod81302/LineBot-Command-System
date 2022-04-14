<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLineUsers extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('line_users', function (Blueprint $table) {
			$table->char('id',33);
			$table->string('latest_name',80);
			$table->string('latest_img_url',300)->nullable();
			$table->enum('status',['follow','unfollow'])->default('unfollow');
			$table->timestamps();
			$table->primary('id');
		});
		
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('line_users');
	}
}
