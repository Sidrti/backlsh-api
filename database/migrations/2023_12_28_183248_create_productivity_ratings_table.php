<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductivityRatingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('productivity_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('process_id')->nullable();
            $table->integer('productivity_status');
            $table->integer('process_type');
            $table->string('website_url', 500)->nullable();

            // Foreign Key
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('process_id')->references('id')->on('processes');

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
        Schema::dropIfExists('productivity_ratings');
    }
}
