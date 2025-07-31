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
        // Add indexes to user_activities table
        Schema::table('user_activities', function (Blueprint $table) {
            // Composite index for user + date range queries
            $table->index(['user_id', 'start_datetime', 'end_datetime'], 'user_activities_user_datetime_idx');
            
            // Index for productivity status filtering
            $table->index('productivity_status', 'user_activities_status_idx');
            
            // Index for process_id joins
            $table->index('process_id', 'user_activities_process_idx');
            
            // Single column index for date-based queries
            $table->index('start_datetime', 'user_activities_start_datetime_idx');
        });

        // Add indexes to user_sub_activities table
        Schema::table('user_sub_activities', function (Blueprint $table) {
            // Composite index for activity + date queries
            $table->index(['user_activity_id', 'start_datetime'], 'sub_activities_activity_datetime_idx');
            
            // Index for productivity status filtering
            $table->index('productivity_status', 'sub_activities_status_idx');
            
            // Index for process_id joins
            $table->index('process_id', 'sub_activities_process_idx');
            
            // Index for website URL queries
            $table->index('website_url', 'sub_activities_website_idx');
        });

        // Add indexes to processes table
        Schema::table('processes', function (Blueprint $table) {
            // Index for process name lookups
            $table->index('process_name', 'processes_name_idx');
            
            // Index for process type filtering
            $table->index('type', 'processes_type_idx');
        });

        // Add indexes to user_productivity_summaries table
        Schema::table('user_productivity_summaries', function (Blueprint $table) {
            // Composite index for date range reports
            $table->index(['user_id', 'date'], 'productivity_summaries_user_date_idx');
            
            // Index for single-date queries
            $table->index('date', 'productivity_summaries_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes from user_activities
        Schema::table('user_activities', function (Blueprint $table) {
            $table->dropIndex('user_activities_user_datetime_idx');
            $table->dropIndex('user_activities_status_idx');
            $table->dropIndex('user_activities_process_idx');
            $table->dropIndex('user_activities_start_datetime_idx');
        });

        // Remove indexes from user_sub_activities
        Schema::table('user_sub_activities', function (Blueprint $table) {
            $table->dropIndex('sub_activities_activity_datetime_idx');
            $table->dropIndex('sub_activities_status_idx');
            $table->dropIndex('sub_activities_process_idx');
            $table->dropIndex('sub_activities_website_idx');
        });

        // Remove indexes from processes
        Schema::table('processes', function (Blueprint $table) {
            $table->dropIndex('processes_name_idx');
            $table->dropIndex('processes_type_idx');
        });

        // Remove indexes from user_productivity_summaries
        Schema::table('user_productivity_summaries', function (Blueprint $table) {
            $table->dropIndex('productivity_summaries_user_date_idx');
            $table->dropIndex('productivity_summaries_date_idx');
        });
    }
};