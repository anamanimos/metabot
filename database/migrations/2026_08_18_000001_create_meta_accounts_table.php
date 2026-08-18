<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_name');
            $table->string('session_folder')->unique();
            $table->enum('status', ['active', 'login_required'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_accounts');
    }
};
