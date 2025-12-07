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
        Schema::table('user_activities', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained('projects')->onDelete('set null');
            $table->foreignId('task_id')->nullable()->after('project_id')->constrained('tasks')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_activities', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['task_id']);
            $table->dropColumn(['project_id', 'task_id']);
        });
    }
};
