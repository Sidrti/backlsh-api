<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys
            $table->foreignId('task_id')->nullable()->constrained('tasks')->onDelete('set null');
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('reported_by')->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            
            // Issue Details
            $table->string('title'); // Recommended: add a title field
            $table->text('description')->nullable(); // Recommended: add a description field
            
            // Enums/Strings for status and types
            $table->enum('severity', ['LOW', 'MEDIUM', 'HIGH'])->default('MEDIUM');
            $table->enum('type', ['BUG', 'IMPROVEMENT', 'REQUIREMENT'])->default('BUG');
            $table->enum('status', ['TODO', 'IN_PROGRESS', 'DONE'])->default('TODO');
            
            $table->integer('reopen_count')->default(0);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};