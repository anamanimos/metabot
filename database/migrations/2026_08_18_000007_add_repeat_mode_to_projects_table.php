<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('repeat_type', ['continuous', 'once', 'until_date'])->default('continuous')->after('images_per_post');
            $table->date('start_date')->nullable()->after('repeat_type');
            $table->date('end_date')->nullable()->after('start_date');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['repeat_type', 'start_date', 'end_date']);
        });
    }
};
