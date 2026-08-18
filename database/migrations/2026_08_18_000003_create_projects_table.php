<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('meta_account_id')->nullable()->constrained('meta_accounts')->nullOnDelete();
            $table->string('portfolio_name');
            $table->string('target_time');
            $table->integer('images_per_post')->default(1);
            $table->json('exclude_days')->nullable();
            $table->boolean('is_continuous')->default(true);
            $table->enum('status', ['active', 'paused', 'completed'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
