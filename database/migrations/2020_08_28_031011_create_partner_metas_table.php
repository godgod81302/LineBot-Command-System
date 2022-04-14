<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartnerMetasTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('partner_metas', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('partner_id');
			$table->char('name',64);
			$table->string('value',1200);
			$table->timestamps();
			
			$table->foreign('partner_id')
				->references('id')
				->on('partners')
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
		Schema::dropIfExists('partner_metas');
	}
}
