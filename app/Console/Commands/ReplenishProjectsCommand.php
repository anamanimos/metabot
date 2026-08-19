<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReplenishProjectsCommand extends Command
{
    protected $signature = 'projects:replenish';
    protected $description = 'Menambah slot jadwal baru (H+29) sesuai aturan repeat_type (continuous / once / until_date)';

    public function handle()
    {
        $this->info("Memulai Buffer Replenish...");

        $projects = Project::where('status', 'active')->with('mediaFiles')->get();
        if ($projects->isEmpty()) {
            $this->info("Tidak ada project dengan status ACTIVE.");
            return 0;
        }

        $startDate = Carbon::today()->addDay();
        $replenishedCount = 0;

        foreach ($projects as $project) {
            if ($project->repeat_type === 'once') {
                $hasPending = Schedule::where('project_id', $project->id)->where('status', 'pending')->exists();
                if (!$hasPending) {
                    $project->update(['status' => 'completed']);
                }
                continue;
            }

            $mediaFiles = $project->mediaFiles;
            if ($mediaFiles->isEmpty()) {
                continue;
            }

            $excludeDays = $project->exclude_days ?? [];
            $imagesPerPost = $project->images_per_post ?: 1;

            for ($i = 0; $i < 29; $i++) {
                $targetDate = $startDate->copy()->addDays($i);

                if ($project->repeat_type === 'until_date' && $project->end_date && $targetDate->gt($project->end_date)) {
                    break;
                }

                if (in_array($targetDate->dayOfWeek, $excludeDays)) {
                    continue;
                }

                $dateStr = $targetDate->format('Y-m-d');
                $exists = Schedule::where('portfolio_name', $project->portfolio_name)
                    ->where('target_date', $dateStr)
                    ->where('target_time', $project->target_time)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $paths = [];
                $scheduleCount = Schedule::where('project_id', $project->id)->count();
                $mediaIndex = $scheduleCount * $imagesPerPost;

                for ($imgIdx = 0; $imgIdx < $imagesPerPost; $imgIdx++) {
                    $pickedMedia = $mediaFiles[($mediaIndex + $imgIdx) % $mediaFiles->count()];
                    $paths[] = $pickedMedia->file_path;
                }

                $primaryPath = $paths[0] ?? '';
                $itemCode = 'proj_' . $project->id . '_' . $dateStr . '_' . rand(10, 99);

                Schedule::create([
                    'project_id' => $project->id,
                    'item_code' => $itemCode,
                    'portfolio_name' => $project->portfolio_name,
                    'media_path' => $primaryPath,
                    'media_paths' => $paths,
                    'target_date' => $dateStr,
                    'target_time' => $project->target_time,
                    'status' => 'pending',
                    'notes' => "Buffer Project '{$project->name}' (" . strtoupper($project->repeat_type) . ")",
                ]);

                $replenishedCount++;
            }

            if ($project->repeat_type === 'until_date' && $project->end_date && Carbon::today()->gt($project->end_date)) {
                $hasPending = Schedule::where('project_id', $project->id)->where('status', 'pending')->exists();
                if (!$hasPending) {
                    $project->update(['status' => 'completed']);
                }
            }
        }

        $this->info("Selesai! Berhasil menambahkan {$replenishedCount} slot jadwal baru.");
        return 0;
    }
}
