<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recognition_prompts')) {
            Schema::create('recognition_prompts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_id');
                $table->unsignedBigInteger('candidate_user_id');
                $table->string('trigger_key', 64);
                $table->string('title');
                $table->text('message');
                $table->string('suggested_preset', 32)->default('GREAT_WORK');
                $table->enum('status', ['PENDING', 'CONVERTED', 'DISMISSED'])->default('PENDING');
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->foreign('admin_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('candidate_user_id')->references('id')->on('users')->onDelete('cascade');

                $table->index(['candidate_user_id', 'created_at']);
                $table->index(['admin_id', 'status']);
                $table->index(['candidate_user_id', 'trigger_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recognition_prompts');
    }
};
