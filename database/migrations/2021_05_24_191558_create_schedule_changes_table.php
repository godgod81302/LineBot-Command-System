<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScheduleChangesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('schedule_changes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->dateTime('start_time')->comment('預定開始時機間');
			$table->dateTime('end_time')->comment('預定結束時間');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('schedule_changes');
    }
}
