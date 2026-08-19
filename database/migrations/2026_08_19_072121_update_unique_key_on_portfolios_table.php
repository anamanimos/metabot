<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('portfolios', function (Blueprint $table) {
                $table->dropUnique('portfolios_meta_account_id_name_unique');
            });
        } catch (\Exception $e) {
            // Index might not exist or already dropped
        }

        try {
            Schema::table('portfolios', function (Blueprint $table) {
                $table->unique(['meta_account_id', 'combined_target'], 'portfolios_meta_account_id_combined_unique');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
    }

    public function down(): void
    {
        try {
            Schema::table('portfolios', function (Blueprint $table) {
                $table->dropUnique('portfolios_meta_account_id_combined_unique');
                $table->unique(['meta_account_id', 'name']);
            });
        } catch (\Exception $e) {
            // Rollback fallback
        }
    }
};
