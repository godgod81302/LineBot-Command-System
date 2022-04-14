<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoomImgPairsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('room_img_pairs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('image_id');
            $table->unsignedBigInteger('room_data_id');
            $table->set('img_for',['checkpoint','room'])->nullable();

			$table->foreign('image_id')
				->references('id')
				->on('images')
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
        Schema::dropIfExists('room_img_pairs');
    }
}
