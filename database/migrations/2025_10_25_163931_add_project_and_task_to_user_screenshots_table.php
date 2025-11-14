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
        Schema::table('user_screenshots', function (Blueprint $table) {
            // Add project_id after user_activity_id (or adjust as needed)
            $table->foreignId('project_id')
                  ->nullable()
                  ->after('process_id') // Adjust if you want it elsewhere
                  ->constrained('projects') // Assumes your projects table is named 'projects'
                  ->onDelete('set null'); // Set project_id to NULL if the project is deleted

            // Add task_id after project_id
            $table->foreignId('task_id')
                  ->nullable()
                  ->after('project_id')
                  ->constrained('tasks') // Assumes your tasks table is named 'tasks'
                  ->onDelete('set null'); // Set task_id to NULL if the task is deleted
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_screenshots', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['project_id']);
            $table->dropForeign(['task_id']);

            // Drop the columns
            $table->dropColumn(['project_id', 'task_id']);
        });
    }
};