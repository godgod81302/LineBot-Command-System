<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServicePointImgPairsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('service_point_img_pairs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('image_id');
            $table->unsignedBigInteger('service_point_id');

            $table->foreign('service_point_id')
            ->references('id')
            ->on('service_points')
            ->onDelete('cascade');

            $table->foreign('image_id')
            ->references('id')
            ->on('images')
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
        Schema::dropIfExists('service_point_img_pairs');
    }
}
