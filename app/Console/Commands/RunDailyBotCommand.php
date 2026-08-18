<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use Illuminate\Console\Command;

class RunDailyBotCommand extends Command
{
    protected $signature = 'bot:run-daily';
    protected $description = 'Otomatis menambah slot H+29 dan mengeksekusi bot Playwright ke Meta Business Suite jam 00:00 WIB';

    protected function getPythonBinary(): string
    {
        $laragonPython = 'D:\laragon\bin\python\python-3.10\python.exe';
        if (file_exists($laragonPython)) {
            return '"' . $laragonPython . '"';
        }
        $venvPython = base_path('venv/bin/python3');
        if (file_exists($venvPython)) {
            return $venvPython;
        }
        return 'python3';
    }

    public function handle()
    {
        $this->info("⏰ Memulai Otomasi Harian Jam 00:00 WIB...");

        // 1. Eksekusi Replenish Buffer 29 Hari
        $this->call('projects:replenish');

        // 2. Export Pending Schedules to schedule.json
        $schedules = Schedule::where('status', 'pending')
            ->orderBy('target_date')
            ->orderBy('target_time')
            ->get();

        if ($schedules->isEmpty()) {
            $this->info("Tidak ada jadwal PENDING untuk dieksekusi.");
            return 0;
        }

        $exportData = [];
        foreach ($schedules as $item) {
            $paths = $item->media_paths ?? [$item->media_path];
            $exportData[] = [
                'item_code' => $item->item_code,
                'portfolio_name' => $item->portfolio_name,
                'media_path' => $item->media_path,
                'media_paths' => $paths,
                'target_date' => $item->target_date->format('d/m/Y'),
                'target_time' => $item->target_time,
                'notes' => $item->notes,
            ];
        }

        $jsonFile = base_path('schedule.json');
        file_put_contents($jsonFile, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 3. Jalankan Script Playwright scheduler.py
        $basePath = base_path();
        $pythonBin = $this->getPythonBinary();

        $this->info("🚀 Memulai eksekusi Playwright Bot...");

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = "cd /d \"{$basePath}\" && {$pythonBin} scheduler.py";
            exec($cmd);
        } else {
            $cmd = "cd {$basePath} && xvfb-run {$pythonBin} scheduler.py >> storage/logs/bot_runner.log 2>&1";
            exec($cmd);
        }

        $this->info("✅ Auto Bot Harian 00:00 WIB Selesai!");
        return 0;
    }
}
