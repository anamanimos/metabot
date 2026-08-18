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
                'images_per_post' => 'required|integer|min:1|max:10',
                'exclude_days' => 'nullable|array',
                'media_files' => 'required|array|min:1',
                'media_files.*' => 'file|mimes:jpg,jpeg,png,mp4,mov|max:50000',
            ]);

            $account = MetaAccount::find($request->meta_account_id) ?? MetaAccount::first();

            $project = Project::create([
                'name' => trim($request->name),
                'meta_account_id' => $account?->id,
                'portfolio_name' => trim($request->portfolio_name),
                'target_time' => trim($request->target_time),
                'images_per_post' => (int) $request->images_per_post,
                'exclude_days' => array_map('intval', $request->input('exclude_days', [])),
                'is_continuous' => true,
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

            // Inisialisasi Buffer 29 Hari Pertama
            $this->seedInitial29DayBuffer($project);

            $msg = "Project '{$project->name}' berhasil dibuat! Buffer jadwal 29 hari telah diinisialisasi.";

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
                'images_per_post' => 'required|integer|min:1|max:10',
                'exclude_days' => 'nullable|array',
                'media_files' => 'nullable|array',
                'media_files.*' => 'file|mimes:jpg,jpeg,png,mp4,mov|max:50000',
            ]);

            $project->update([
                'name' => trim($request->name),
                'portfolio_name' => trim($request->portfolio_name),
                'target_time' => trim($request->target_time),
                'images_per_post' => (int) $request->images_per_post,
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

    protected function seedInitial29DayBuffer(Project $project)
    {
        $startDate = Carbon::today()->addDay();
        $mediaFiles = $project->mediaFiles;
        if ($mediaFiles->isEmpty()) return;

        $excludeDays = $project->exclude_days ?? [];
        $imagesPerPost = $project->images_per_post;
        $mediaIndex = 0;
        $created = 0;

        for ($i = 0; $i < 29; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
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
                'notes' => "Rolling 29-Day Buffer Project '{$project->name}' (Hari ke-" . ($created + 1) . ")",
            ]);

            $created++;
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
