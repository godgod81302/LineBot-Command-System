<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartnerGroupPair extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('partner_group_pairs', function (Blueprint $table) {
			$table->id();
			$table->timestamp('create_at')->useCurrent();
			$table->unsignedBigInteger('partner_id');
			$table->char('line_group_id',33);
				
			$table->foreign('line_group_id')
				->references('id')
				->on('line_groups')
				->onDelete('cascade');
			$table->foreign('partner_id')
				->references('id')
				->on('partners')
				->onDelete('cascade');
			$table->enum('group_type',['Server','Admin','Booking'])->default('Booking');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('partner_group_pairs');
	}
}
