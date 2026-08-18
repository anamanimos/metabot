<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('item_code')->unique();
            $table->string('portfolio_name');
            $table->foreignId('media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->string('media_path');
            $table->json('media_paths')->nullable();
            $table->date('target_date');
            $table->string('target_time');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['portfolio_name', 'target_date', 'target_time'], 'unique_portfolio_datetime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
