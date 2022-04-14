<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoomServerPairsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('room_server_pairs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('server_id');
            $table->unsignedBigInteger('room_data_id');
            
			$table->foreign('server_id')
				->references('id')
				->on('servers')
				->onDelete('cascade');
            $table->foreign('room_data_id')
            ->references('id')
            ->on('room_data')
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
        Schema::dropIfExists('room_server_pairs');
    }
}
