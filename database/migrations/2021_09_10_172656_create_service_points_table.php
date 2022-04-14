<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServicePointsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('service_points', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('area_id');
            $table->unsignedBigInteger('partner_id');
            $table->char('name',64)->index();
            $table->char('nickname',5)->unique()->nullable();
            $table->char('address')->nullable();
            $table->foreign('area_id')
            ->references('id')
            ->on('areas')
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
        Schema::dropIfExists('service_points');
    }
}
