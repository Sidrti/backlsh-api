<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('attendance_schedules', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('min_hours');
            $table->timestamps();
        });

        Schema::create('attendance_schedule_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_schedule_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
          
            $table->primary(['attendance_schedule_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attendance_schedules');
        Schema::dropIfExists('attendance_schedule_user');
    }
};
