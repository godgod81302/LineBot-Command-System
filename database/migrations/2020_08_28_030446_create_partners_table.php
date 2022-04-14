<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartnersTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('partners', function (Blueprint $table) {
			$table->id();
			$table->string('name',128);
			$table->string('nickname',1)->unique();
			$table->enum('status',['regular','temp'])->default('regular');
			$table->string('location',64)->nullable();
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
		Schema::dropIfExists('partners');
	}
}
