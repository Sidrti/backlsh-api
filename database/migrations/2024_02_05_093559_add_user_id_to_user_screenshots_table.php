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
        Schema::table('user_screenshots', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->after('process_id');
            $table->string('website_url', 500)->nullable()->after('process_id');
            $table->foreign('user_id')->references('id')->on('users');
         
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_screenshots', function (Blueprint $table) {
            //
        });
    }
};
