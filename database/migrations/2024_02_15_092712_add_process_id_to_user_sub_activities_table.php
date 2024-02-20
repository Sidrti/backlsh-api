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
        Schema::table('user_sub_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('process_id')->nullable()->after('user_activity_id');
            $table->foreign('process_id')->references('id')->on('processes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_sub_activities', function (Blueprint $table) {
            $table->dropColumn('process_id');
        });
    }
};
