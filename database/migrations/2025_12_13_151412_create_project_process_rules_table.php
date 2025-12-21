<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_process_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('process', 255);
            $table->enum('match_type', ['exact', 'domain', 'contains'])->default('exact');
            $table->boolean('priority')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for performance
            $table->index(['project_id', 'is_active']);
            $table->index('match_type');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_process_rules');
    }
};
