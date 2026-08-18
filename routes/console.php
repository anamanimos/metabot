<?php

use App\Models\Schedule as ScheduleModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schedule;

// 1. Eksekusi Utama Jam 00:00 WIB (Replenish Buffer 29 Hari)
Schedule::command('bot:run-daily')->dailyAt('00:00');

// 2. Cek Otomatis Setiap 15 Menit jika Ada Jadwal PENDING Hari Ini yang Baru Dibuat
Schedule::command('bot:run-daily')->everyFifteenMinutes()->when(function () {
    return ScheduleModel::where('status', 'pending')
        ->where('target_date', '<=', Carbon::today()->format('Y-m-d'))
        ->exists();
});
