<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTempGroupAdminsTable extends Migration
{

    public function up()
    {
        Schema::create('temp_group_admins', function (Blueprint $table) {
			$table->id();
			$table->char('line_user_id',33)->nullable();
            $table->char('line_group_id',33);
			$table->unsignedBigInteger('partner_id');
			$table->string('nickname',1)->unique();
			
			$table->foreign('line_user_id')
				->references('id')
				->on('line_users')
				->onDelete('cascade');
            $table->foreign('line_group_id')
            ->references('id')
            ->on('line_groups')
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
        Schema::dropIfExists('temp_group_admins');
    }
}
