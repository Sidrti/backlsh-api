<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserScreenshotsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_screenshots', function (Blueprint $table) {
            $table->id();
            // $table->unsignedBigInteger('user_activity_id');
            // $table->unsignedBigInteger('user_sub_activity_id');
            $table->unsignedBigInteger('process_id');
            $table->string('screenshot_path', 500);

            // // Foreign Keys
            $table->foreign('process_id')->references('id')->on('processes');
            // $table->foreign('user_activity_id')->references('id')->on('user_activities');
            // $table->foreign('user_sub_activity_id')->references('id')->on('user_sub_activities');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_screenshots');
    }
}
