<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use App\Models\MetaAccount;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Dapatkan path executable Python yang valid di Laragon / System
     */
    protected function getPythonBinary(): string
    {
        $laragonPython = 'D:\laragon\bin\python\python-3.10\python.exe';
        if (file_exists($laragonPython)) {
            return '"' . $laragonPython . '"';
        }
        return 'python';
    }

    public function index(Request $request)
    {
        $projects = Project::with(['metaAccount', 'mediaFiles', 'schedules' => function ($q) {
            $q->where('status', 'pending')->orderBy('target_date');
        }])->latest()->get();

        $accounts = MetaAccount::with('portfolios')->orderBy('account_name')->get();
        $portfolios = Portfolio::with('metaAccount')->orderBy('name')->get();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'projects' => $projects,
                'accounts' => $accounts,
                'portfolios' => $portfolios,
            ]);
        }

        return view('projects.index', compact('projects', 'accounts', 'portfolios'));
    }

    public function create()
    {
        $accounts = MetaAccount::with('portfolios')->orderBy('account_name')->get();
        $portfolios = Portfolio::with('metaAccount')->orderBy('name')->get();

        return view('projects.create', compact('accounts', 'portfolios'));
    }

    public function show($id)
    {
        $project = Project::with(['metaAccount', 'mediaFiles', 'schedules' => function ($q) {
            $q->orderBy('target_date', 'asc');
        }])->findOrFail($id);

        $furthestDate = $project->schedules()->where('status', 'pending')->max('target_date');
        $furthestDateFormatted = $furthestDate ? Carbon::parse($furthestDate)->translatedFormat('d F Y') : 'Belum Dijadwalkan';

        $pendingCount = $project->schedules()->where('status', 'pending')->count();
        $completedCount = $project->schedules()->where('status', 'completed')->count();

        return view('projects.show', compact('project', 'furthestDateFormatted', 'pendingCount', 'completedCount'));
    }

    public function edit($id)
    {
        $project = Project::with(['metaAccount', 'mediaFiles'])->findOrFail($id);
        $accounts = MetaAccount::with('portfolios')->orderBy('account_name')->get();
        $portfolios = Portfolio::with('metaAccount')->orderBy('name')->get();

        return view('projects.edit', compact('project', 'accounts', 'portfolios'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'meta_account_id' => 'nullable|exists:meta_accounts,id',
                'portfolio_name' => 'required|string',
                'target_time' => 'required|string',
                'images_per_post' => 'nullable|integer|min:1|max:10',
                'repeat_type' => 'required|in:continuous,once,until_date',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'exclude_days' => 'nullable|array',
                'media_files' => 'required|array|min:1',
                'media_files.*' => 'file|mimes:jpg,jpeg,png,mp4,mov|max:50000',
            ]);

            // Validasi Aturan 1x Post: Minimal 30 menit dari jam sekarang
            if ($request->repeat_type === 'once') {
                $targetDateStr = $request->start_date ? Carbon::parse($request->start_date)->format('Y-m-d') : Carbon::today()->format('Y-m-d');
                $targetDateTimeStr = $targetDateStr . ' ' . trim($request->target_time);
                $scheduledAt = Carbon::parse($targetDateTimeStr);
                $minAllowedTime = Carbon::now()->addMinutes(30);

                if ($scheduledAt->lt($minAllowedTime)) {
                    $minTimeFormatted = $minAllowedTime->format('H:i');
                    $msg = "Untuk mode 1x Post, waktu penjadwalan minimal 30 menit dari jam sekarang (minimal jam {$minTimeFormatted} WIB).";
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => $msg,
                        ], 422);
                    }
                    return redirect()->back()->with('error', $msg);
                }
            }

            $account = MetaAccount::find($request->meta_account_id) ?? MetaAccount::first();

            $project = Project::create([
                'name' => trim($request->name),
                'meta_account_id' => $account?->id,
                'portfolio_name' => trim($request->portfolio_name),
                'target_time' => trim($request->target_time),
                'images_per_post' => (int) ($request->images_per_post ?? 1),
                'repeat_type' => $request->repeat_type,
                'start_date' => $request->start_date ? Carbon::parse($request->start_date) : Carbon::today(),
                'end_date' => $request->end_date ? Carbon::parse($request->end_date) : null,
                'exclude_days' => array_map('intval', $request->input('exclude_days', [])),
                'is_continuous' => ($request->repeat_type === 'continuous'),
                'status' => 'active',
            ]);

            $uploadedMediaIds = [];
            $destinationDir = public_path('storage/uploads');
            if (!file_exists($destinationDir)) {
                mkdir($destinationDir, 0777, true);
            }

            foreach ($request->file('media_files') as $file) {
                if ($file->isValid()) {
                    $fileName = time() . '_' . rand(100, 999) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                    $file->move($destinationDir, $fileName);
                    $savedFilePath = $destinationDir . '/' . $fileName;
                    $filePublicUrl = '/storage/uploads/' . $fileName;

                    if (file_exists($savedFilePath)) {
                        $fileHash = hash_file('sha256', $savedFilePath);
                        $existing = MediaFile::where('file_hash', $fileHash)->first();
                        
                        if ($existing) {
                            $uploadedMediaIds[] = $existing->id;
                            @unlink($savedFilePath);
                        } else {
                            $media = MediaFile::create([
                                'original_name' => $file->getClientOriginalName(),
                                'file_path' => $filePublicUrl,
                                'file_hash' => $fileHash,
                                'mime_type' => $file->getClientMimeType(),
                                'file_size' => filesize($savedFilePath),
                            ]);
                            $uploadedMediaIds[] = $media->id;
                        }
                    }
                }
            }

            $project->mediaFiles()->sync($uploadedMediaIds);

            // Inisialisasi Buffer Sesuai Mode Repeat
            $this->seedInitialBufferByMode($project);

            $msg = "Project '{$project->name}' (" . strtoupper($project->repeat_type) . ") berhasil dibuat!";

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'redirect' => route('projects.show', $project->id),
                ]);
            }

            return redirect()->route('projects.show', $project->id)->with('success', $msg);

        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal membuat project: ' . $e->getMessage(),
                ], 500);
            }
            return redirect()->back()->with('error', 'Gagal membuat project: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $project = Project::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255',
                'portfolio_name' => 'required|string',
                'target_time' => 'required|string',
                'images_per_post' => 'nullable|integer|min:1|max:10',
                'repeat_type' => 'required|in:continuous,once,until_date',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'exclude_days' => 'nullable|array',
                'media_files' => 'nullable|array',
                'media_files.*' => 'file|mimes:jpg,jpeg,png,mp4,mov|max:50000',
            ]);

            // Validasi Aturan 1x Post: Minimal 30 menit dari jam sekarang
            if ($request->repeat_type === 'once') {
                $targetDateStr = $request->start_date ? Carbon::parse($request->start_date)->format('Y-m-d') : Carbon::today()->format('Y-m-d');
                $targetDateTimeStr = $targetDateStr . ' ' . trim($request->target_time);
                $scheduledAt = Carbon::parse($targetDateTimeStr);
                $minAllowedTime = Carbon::now()->addMinutes(30);

                if ($scheduledAt->lt($minAllowedTime)) {
                    $minTimeFormatted = $minAllowedTime->format('H:i');
                    $msg = "Untuk mode 1x Post, waktu penjadwalan minimal 30 menit dari jam sekarang (minimal jam {$minTimeFormatted} WIB).";
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => $msg,
                        ], 422);
                    }
                    return redirect()->back()->with('error', $msg);
                }
            }

            $project->update([
                'name' => trim($request->name),
                'portfolio_name' => trim($request->portfolio_name),
                'target_time' => trim($request->target_time),
                'images_per_post' => (int) ($request->images_per_post ?? 1),
                'repeat_type' => $request->repeat_type,
                'start_date' => $request->start_date ? Carbon::parse($request->start_date) : $project->start_date,
                'end_date' => $request->end_date ? Carbon::parse($request->end_date) : null,
                'exclude_days' => array_map('intval', $request->input('exclude_days', [])),
            ]);

            if ($request->hasFile('media_files')) {
                $destinationDir = public_path('storage/uploads');
                if (!file_exists($destinationDir)) {
                    mkdir($destinationDir, 0777, true);
                }

                $newMediaIds = [];
                foreach ($request->file('media_files') as $file) {
                    if ($file->isValid()) {
                        $fileName = time() . '_' . rand(100, 999) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                        $file->move($destinationDir, $fileName);
                        $savedFilePath = $destinationDir . '/' . $fileName;
                        $filePublicUrl = '/storage/uploads/' . $fileName;

                        if (file_exists($savedFilePath)) {
                            $fileHash = hash_file('sha256', $savedFilePath);
                            $existing = MediaFile::where('file_hash', $fileHash)->first();

                            if ($existing) {
                                $newMediaIds[] = $existing->id;
                                @unlink($savedFilePath);
                            } else {
                                $media = MediaFile::create([
                                    'original_name' => $file->getClientOriginalName(),
                                    'file_path' => $filePublicUrl,
                                    'file_hash' => $fileHash,
                                    'mime_type' => $file->getClientMimeType(),
                                    'file_size' => filesize($savedFilePath),
                                ]);
                                $newMediaIds[] = $media->id;
                            }
                        }
                    }
                }
                $project->mediaFiles()->attach($newMediaIds);
            }

            Schedule::where('project_id', $project->id)
                ->where('status', 'pending')
                ->update(['target_time' => $project->target_time]);

            $msg = "Project '{$project->name}' berhasil diperbarui!";

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'redirect' => route('projects.show', $project->id),
                ]);
            }

            return redirect()->route('projects.show', $project->id)->with('success', $msg);

        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memperbarui project: ' . $e->getMessage(),
                ], 500);
            }
            return redirect()->back()->with('error', 'Gagal memperbarui project: ' . $e->getMessage());
        }
    }

    public function addMedia(Request $request, $id)
    {
        try {
            $project = Project::findOrFail($id);

            $request->validate([
                'media_files' => 'required|array|min:1',
                'media_files.*' => 'file|mimes:jpg,jpeg,png,mp4,mov|max:50000',
            ]);

            $destinationDir = public_path('storage/uploads');
            if (!file_exists($destinationDir)) {
                mkdir($destinationDir, 0777, true);
            }

            $newMediaIds = [];
            foreach ($request->file('media_files') as $file) {
                if ($file->isValid()) {
                    $fileName = time() . '_' . rand(100, 999) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                    $file->move($destinationDir, $fileName);
                    $savedFilePath = $destinationDir . '/' . $fileName;
                    $filePublicUrl = '/storage/uploads/' . $fileName;

                    if (file_exists($savedFilePath)) {
                        $fileHash = hash_file('sha256', $savedFilePath);
                        $existing = MediaFile::where('file_hash', $fileHash)->first();

                        if ($existing) {
                            $newMediaIds[] = $existing->id;
                            @unlink($savedFilePath);
                        } else {
                            $media = MediaFile::create([
                                'original_name' => $file->getClientOriginalName(),
                                'file_path' => $filePublicUrl,
                                'file_hash' => $fileHash,
                                'mime_type' => $file->getClientMimeType(),
                                'file_size' => filesize($savedFilePath),
                            ]);
                            $newMediaIds[] = $media->id;
                        }
                    }
                }
            }

            $project->mediaFiles()->attach($newMediaIds);

            $msg = "Berhasil menambahkan " . count($newMediaIds) . " media baru ke pool project '{$project->name}'!";

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                ]);
            }

            return redirect()->back()->with('success', $msg);
        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menambah media: ' . $e->getMessage(),
                ], 500);
            }
            return redirect()->back()->with('error', 'Gagal menambah media: ' . $e->getMessage());
        }
    }

    protected function seedInitialBufferByMode(Project $project)
    {
        $mediaFiles = $project->mediaFiles;
        if ($mediaFiles->isEmpty()) return;

        $excludeDays = $project->exclude_days ?? [];
        $imagesPerPost = $project->images_per_post ?: 1;
        $mediaIndex = 0;

        // MODE 1: ONCE (Hanya 1x Post)
        if ($project->repeat_type === 'once') {
            $targetDate = $project->start_date ? Carbon::parse($project->start_date) : Carbon::today();
            $dateStr = $targetDate->format('Y-m-d');

            $paths = [];
            for ($imgIdx = 0; $imgIdx < $imagesPerPost; $imgIdx++) {
                $pickedMedia = $mediaFiles[$imgIdx % $mediaFiles->count()];
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
                'notes' => "Schedule Single Post Project '{$project->name}'",
            ]);
            return;
        }

        // MODE 2 & 3: CONTINUOUS & UNTIL_DATE
        $startDate = $project->start_date ? Carbon::parse($project->start_date) : Carbon::today()->addDay();
        $limitDays = 29;

        for ($i = 0; $i < $limitDays; $i++) {
            $currentDate = $startDate->copy()->addDays($i);

            // Jika mode until_date dan sudah melewati end_date
            if ($project->repeat_type === 'until_date' && $project->end_date && $currentDate->gt($project->end_date)) {
                break;
            }

            if (in_array($currentDate->dayOfWeek, $excludeDays)) {
                continue;
            }

            $dateStr = $currentDate->format('Y-m-d');
            $exists = Schedule::where('portfolio_name', $project->portfolio_name)
                ->where('target_date', $dateStr)
                ->where('target_time', $project->target_time)
                ->exists();

            if ($exists) continue;

            $paths = [];
            for ($imgIdx = 0; $imgIdx < $imagesPerPost; $imgIdx++) {
                $pickedMedia = $mediaFiles[$mediaIndex % $mediaFiles->count()];
                $paths[] = asset($pickedMedia->file_path);
                $mediaIndex++;
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
        }
    }

    public function toggleStatus(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $project->status = ($project->status === 'active') ? 'paused' : 'active';
        $project->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Status project '{$project->name}' diubah menjadi " . strtoupper($project->status) . '.',
                'status' => $project->status,
            ]);
        }

        return redirect()->back()->with('success', "Status project '{$project->name}' diubah.");
    }
}
