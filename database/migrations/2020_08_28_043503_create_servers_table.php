<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServersTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('servers', function (Blueprint $table) {
			$table->id();
			$table->timestamps();
			$table->char('line_user_id',33)->nullable();
			$table->char('line_group_id',33)->nullable();
			$table->foreignId('partner_id')->nullable()->constrained()->onDelete('set null');
			$table->foreignId('broker_id')->nullable()->constrained()->onDelete('set null');
			$table->foreignId('country_id')->nullable()->constrained()->onDelete('set null');
			$table->foreignId('area_id')->nullable()->constained()->onDelete('set null');
			$table->char('name',64)->index();
			$table->set('lanague',['中文','英文'])->nullable();
			$table->set('service_type',['定點','外送','按摩'])->nullable();
			$table->smallInteger('height')->comment('身高')->nullable();
			$table->smallInteger('weight')->comment('體重')->nullable();
			$table->char('cup',2)->comment('罩杯大小')->nullable();
			$table->smallInteger('age')->comment('年紀')->nullable();
			$table->dateTime('start_time')->comment('服務開始時間')->nullable();
			$table->dateTime('end_time')->comment('服務結束時間')->nullable();
			$table->text('description')->comment('介紹標籤')->nullable();
			$table->enum('enable',['Y','N'])->default('Y');

			$table->foreign('line_group_id')
				->references('id')
				->on('line_groups')
				->onDelete('set null');
			$table->foreign('line_user_id')
				->references('id')
				->on('line_users')
				->onDelete('set null');
		});
		
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('servers');
	}
}
