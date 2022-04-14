<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupAdminsTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('group_admins', function (Blueprint $table) {
			$table->id();
			$table->char('line_user_id',33)->nullable()->unique();
			$table->unsignedBigInteger('partner_id');
			$table->string('nickname',1)->unique();
			$table->timestamps();
			
			$table->foreign('line_user_id')
				->references('id')
				->on('line_users')
				->onDelete('cascade');
				
			$table->foreign('partner_id')
				->references('id')
				->on('partners')
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
		Schema::dropIfExists('group_admins');
	}
}
