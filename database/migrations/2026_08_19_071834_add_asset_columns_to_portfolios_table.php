<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->string('portfolio_name')->nullable()->after('name');
            $table->string('asset_name')->nullable()->after('portfolio_name');
            $table->string('asset_type')->nullable()->after('asset_name');
            $table->string('combined_target')->nullable()->after('asset_type');
        });
    }

    public function down(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropColumn(['portfolio_name', 'asset_name', 'asset_type', 'combined_target']);
        });
    }
};
