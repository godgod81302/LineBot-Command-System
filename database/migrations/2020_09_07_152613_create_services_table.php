<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServicesTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('services', function (Blueprint $table) {
			$table->id();
			$table->timestamps();
			$table->foreignId('server_id')->constrained()->onDelete('cascade');
			$table->string('name',64)->nullable();
			$table->string('description')->nullable();
			$table->foreign('name')
			->references('name')
			->on('service_lists')
			->onUpdate('cascade')
			->onDelete('cascade');
			$table->enum('s_time', ['1','2','3','4','5','6','n'])->default(1);
			$table->integer('server_fee')->comment('服務員費用')->default(0);
			$table->integer('broker_fee')->comment('經紀人費用')->default(0);
			$table->integer('company_cost')->comment('店家成本')->default(0);
			$table->integer('company_profit')->comment('店家毛利')->default(0);
			$table->integer('marketing_cost')->comment('銷售成本')->default(0);
			$table->integer('sales_profit')->comment('業務毛利')->default(0);
			$table->integer('period')->comment('服務時間長')->default(0);
			$table->enum('enable',['show','hide'])->default('show');

		});

	}
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('services');
	}
}
