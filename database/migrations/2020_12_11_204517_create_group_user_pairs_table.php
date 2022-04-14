<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupUserPairsTable extends Migration
	{
		/**
		 * Run the migrations.
		*
		* @return void
		*/
		public function up()
		{
			Schema::create('group_user_pairs', function (Blueprint $table) {
				$table->id();
				$table->char('line_group_id',33);
				$table->char('line_user_id',33);
			});
		}

		/**
		 * Reverse the migrations.
		*
		* @return void
		*/
		public function down()
		{
			Schema::dropIfExists('group_user_pairs');
		}
	}
