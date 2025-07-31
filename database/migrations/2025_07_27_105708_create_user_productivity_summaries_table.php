<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_productivity_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('date');
            $table->unsignedInteger('productive_seconds')->default(0);
            $table->unsignedInteger('nonproductive_seconds')->default(0);
            $table->unsignedInteger('neutral_seconds')->default(0);
            $table->unsignedInteger('total_seconds')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_productivity_summaries');
    }
};