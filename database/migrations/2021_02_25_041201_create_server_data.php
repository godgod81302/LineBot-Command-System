<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServerData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('server_create_data', function (Blueprint $table) {
            //
            $table->id();
            $table->char('line_user_id',33)->nullable();
			$table->char('line_group_id',33)->nullable();
			$table->foreignId('partner_id')->nullable()->constrained()->onDelete('set null');
			$table->foreignId('broker_id')->nullable()->constrained()->onDelete('set null');
            $table->char('duty_start_time',5)->nullable();
			$table->char('duty_end_time',5)->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->onDelete('set null');
            $table->set('lanague',['中文','英文'])->nullable();
            $table->set('service_type',['定點','外送','按摩'])->nullable();
			$table->char('name',64)->index();
            $table->foreignId('area_id')->nullable()->constained()->onDelete('set null');
            $table->smallInteger('height')->comment('身高')->nullable();
            $table->smallInteger('weight')->comment('體重')->nullable();
            $table->char('cup',2)->comment('罩杯大小')->nullable();
            $table->smallInteger('age')->comment('年紀')->nullable();
            $table->text('description')->comment('介紹標籤')->nullable();
            $table->text('services')->comment('方案')->nullable();
            $table->text('special_service')->comment('特別服務')->nullable();
            
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
        Schema::table('Server_Create_data', function (Blueprint $table) {
            //
        });
    }
}
