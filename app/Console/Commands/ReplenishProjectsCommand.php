<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReplenishProjectsCommand extends Command
{
    protected $signature = 'projects:replenish';
    protected $description = 'Menambah 1 slot jadwal baru (H+29) untuk setiap project aktif agar buffer rolling 29 hari selalu penuh';

    public function handle()
    {
        $this->info("Memulai Rolling 29-Day Buffer Replenish...");

        $projects = Project::where('status', 'active')->with('mediaFiles')->get();
        if ($projects->isEmpty()) {
            $this->info("Tidak ada project dengan status ACTIVE.");
            return 0;
        }

        $startDate = Carbon::today()->addDay();
        $replenishedCount = 0;

        foreach ($projects as $project) {
            $mediaFiles = $project->mediaFiles;
            if ($mediaFiles->isEmpty()) {
                continue;
            }

            $excludeDays = $project->exclude_days ?? [];
            $imagesPerPost = $project->images_per_post;

            // Kita pastikan antrean terisi 29 hari ke depan dari besok
            for ($i = 0; $i < 29; $i++) {
                $targetDate = $startDate->copy()->addDays($i);

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

                // Pick media files for this post
                $paths = [];
                $scheduleCount = Schedule::where('project_id', $project->id)->count();
                $mediaIndex = $scheduleCount * $imagesPerPost;

                for ($imgIdx = 0; $imgIdx < $imagesPerPost; $imgIdx++) {
                    $pickedMedia = $mediaFiles[($mediaIndex + $imgIdx) % $mediaFiles->count()];
                    $paths[] = asset($pickedMedia->file_path);
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
                    'notes' => "Rolling 29-Day Buffer Project '{$project->name}'",
                ]);

                $replenishedCount++;
            }
        }

        $this->info("Selesai! Berhasil menambahkan {$replenishedCount} slot jadwal baru untuk buffer 29 hari.");
        return 0;
    }
}
