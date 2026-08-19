<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE schedules SET media_path = REPLACE(media_path, 'http://localhost:8000', '') WHERE media_path LIKE '%localhost%'");
        DB::statement("UPDATE schedules SET media_path = REPLACE(media_path, 'http://127.0.0.1:8000', '') WHERE media_path LIKE '%127.0.0.1%'");
        
        DB::statement("UPDATE schedules SET media_paths = REPLACE(media_paths, 'http://localhost:8000', '') WHERE media_paths LIKE '%localhost%'");
        DB::statement("UPDATE schedules SET media_paths = REPLACE(media_paths, 'http://127.0.0.1:8000', '') WHERE media_paths LIKE '%127.0.0.1%'");
    }

    public function down(): void
    {
        //
    }
};
