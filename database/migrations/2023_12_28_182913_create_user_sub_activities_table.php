<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserSubActivitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_sub_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_activity_id');
            $table->string('title', 400);
            $table->string('website_url', 500)->nullable();
            $table->string('productivity_status',30);
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');

            // Foreign Key
            $table->foreign('user_activity_id')->references('id')->on('user_activities');

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
        Schema::dropIfExists('user_sub_activities');
    }
}
