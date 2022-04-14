<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartnerSalesAuth extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('partner_sales_auth', function (Blueprint $table) {
			$table->id();
			$table->timestamp('create_at')->useCurrent();
			$table->foreignId('partner_id')->constrained()->onDelete('cascade');
			$table->foreignId('sales_id')->constrained('sales')->onDelete('cascade');
			$table->char('sales_group_code',5);
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('partner_sales_auth');
	}
}
