<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use App\Models\MetaAccount;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
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
        $venvPython = base_path('venv/bin/python3');
        if (file_exists($venvPython)) {
            return $venvPython;
        }
        return 'python3';
    }

    public function index(Request $request)
    {
        $portfolios = Portfolio::orderBy('name')->get();
        if ($portfolios->isEmpty()) {
            Portfolio::create(['name' => 'Sevencols']);
            $portfolios = Portfolio::orderBy('name')->get();
        }

        $accounts = MetaAccount::orderBy('account_name')->get();
        $projects = Project::with('mediaFiles')->latest()->get();

        $statusFilter = $request->get('status', 'all');
        $query = Schedule::with(['mediaFile', 'project'])->latest();
        
        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }
        
        $schedules = $query->paginate(30);

        $stats = [
            'total' => Schedule::count(),
            'pending' => Schedule::where('status', 'pending')->count(),
            'completed' => Schedule::where('status', 'completed')->count(),
            'failed' => Schedule::where('status', 'failed')->count(),
        ];

        return view('schedules.index', compact('portfolios', 'accounts', 'projects', 'schedules', 'stats', 'statusFilter'));
    }

    public function destroy(Request $request, $id)
    {
        $schedule = Schedule::findOrFail($id);
        $schedule->delete();

        $stats = [
            'total' => Schedule::count(),
            'pending' => Schedule::where('status', 'pending')->count(),
            'completed' => Schedule::where('status', 'completed')->count(),
            'failed' => Schedule::where('status', 'failed')->count(),
        ];

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Jadwal berhasil dihapus.',
                'stats' => $stats,
            ]);
        }

        return redirect()->back()->with('success', 'Jadwal berhasil dihapus.');
    }

    /**
     * Kirim Ulang Seluruh Item Antrean yang Error / Failed
     */
    public function retryFailed(Request $request)
    {
        try {
            $failedCount = Schedule::where('status', 'failed')->count();
            if ($failedCount === 0) {
                $msg = 'Tidak ada item antrean yang berstatus FAILED saat ini.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $msg]);
                }
                return redirect()->back()->with('info', $msg);
            }

            Schedule::where('status', 'failed')->update([
                'status' => 'pending',
                'notes' => 'Di-reset untuk dikirim ulang via Web UI',
                'updated_at' => now(),
            ]);

            return $this->syncAndRunBot($request);

        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Gagal mereset item failed: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Gagal mereset item failed: ' . $e->getMessage());
        }
    }

    /**
     * Kirim Satuan Item Schedule Tertentu Secara Langsung
     */
    public function runSingle(Request $request, $id)
    {
        try {
            $schedule = Schedule::with(['mediaFile', 'project'])->findOrFail($id);
            
            $schedule->status = 'pending';
            $schedule->notes = 'Diproses pengiriman satuan via Web UI pada ' . now()->format('H:i');
            $schedule->save();

            $mediaPaths = $schedule->media_paths;
            if (empty($mediaPaths)) {
                $mediaPaths = [$schedule->media_path];
            }

            $localMediaPaths = [];
            foreach ($mediaPaths as $mp) {
                if (!str_starts_with($mp, 'http://') && !str_starts_with($mp, 'https://')) {
                    $localFilePath = public_path(str_replace('/storage', 'storage', $mp));
                    if (file_exists($localFilePath)) {
                        $localMediaPaths[] = $localFilePath;
                    } else {
                        $localMediaPaths[] = $mp;
                    }
                } else {
                    $localMediaPaths[] = $mp;
                }
            }

            $jsonExport = [[
                'id' => $schedule->item_code,
                'portfolioName' => $schedule->portfolio_name,
                'mediaPath' => $localMediaPaths[0] ?? $schedule->media_path,
                'mediaPaths' => $localMediaPaths,
                'date' => $schedule->target_date->format('Y-m-d'),
                'time' => $schedule->target_time,
            ]];

            $scheduleJsonPath = base_path('schedule.json');
            @file_put_contents($scheduleJsonPath, json_encode($jsonExport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $basePath = base_path();
            $pythonBin = $this->getPythonBinary();

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "start \"Meta Story Auto Scheduler Bot (Satuan)\" cmd /k \"cd /d \"{$basePath}\" && {$pythonBin} scheduler.py\"";
                pclose(popen($cmd, "r"));
            } else {
                $venvPython = file_exists(base_path('venv/bin/python3')) ? base_path('venv/bin/python3') : 'python3';
                $logFile = base_path('storage/logs/bot_runner.log');
                @touch($logFile);
                @chmod($logFile, 0777);

                $cmd = "export PLAYWRIGHT_BROWSERS_PATH=/var/www/meta.damaijaya.my.id/ms-playwright && cd \"{$basePath}\" && xvfb-run -a {$venvPython} scheduler.py >> storage/logs/bot_runner.log 2>&1 &";
                exec($cmd, $output, $returnVar);
            }

            $msg = "Berhasil memicu pengiriman satuan untuk item '{$schedule->item_code}'! Bot Playwright sedang memproses di latar belakang.";

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'item_code' => $schedule->item_code
                ]);
            }

            return redirect()->back()->with('success', $msg);

        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Gagal mengirim satuan: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Gagal mengirim satuan: ' . $e->getMessage());
        }
    }

    public function syncAndRunBot(Request $request)
    {
        try {
            $pendingSchedules = Schedule::with(['mediaFile', 'project'])
                ->where('status', 'pending')
                ->orderBy('target_date')
                ->orderBy('target_time')
                ->get();

            if ($pendingSchedules->isEmpty()) {
                $msg = 'Tidak ada antrean berstatus PENDING. Seluruh jadwal Story saat ini sudah COMPLETED!';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $msg,
                        'count' => 0,
                    ]);
                }
                return redirect()->back()->with('error', $msg);
            }

            $jsonExport = [];
            foreach ($pendingSchedules as $s) {
                $mediaPaths = $s->media_paths;
                if (empty($mediaPaths)) {
                    $mediaPaths = [$s->media_path];
                }

                $localMediaPaths = [];
                foreach ($mediaPaths as $mp) {
                    if (!str_starts_with($mp, 'http://') && !str_starts_with($mp, 'https://')) {
                        $localFilePath = public_path(str_replace('/storage', 'storage', $mp));
                        if (file_exists($localFilePath)) {
                            $localMediaPaths[] = $localFilePath;
                        } else {
                            $localMediaPaths[] = $mp;
                        }
                    } else {
                        $localMediaPaths[] = $mp;
                    }
                }

                $jsonExport[] = [
                    'id' => $s->item_code,
                    'portfolioName' => $s->portfolio_name,
                    'mediaPath' => $localMediaPaths[0] ?? $s->media_path,
                    'mediaPaths' => $localMediaPaths,
                    'date' => $s->target_date->format('Y-m-d'),
                    'time' => $s->target_time,
                ];
            }

            $scheduleJsonPath = base_path('schedule.json');
            @file_put_contents($scheduleJsonPath, json_encode($jsonExport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $basePath = base_path();
            $pythonBin = $this->getPythonBinary();
            
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "start \"Meta Story Auto Scheduler Bot\" cmd /k \"cd /d \"{$basePath}\" && {$pythonBin} scheduler.py\"";
                pclose(popen($cmd, "r"));
            } else {
                $venvPython = file_exists(base_path('venv/bin/python3')) ? base_path('venv/bin/python3') : 'python3';
                $logFile = base_path('storage/logs/bot_runner.log');
                @touch($logFile);
                @chmod($logFile, 0777);

                $cmd = "export PLAYWRIGHT_BROWSERS_PATH=/var/www/meta.damaijaya.my.id/ms-playwright && cd \"{$basePath}\" && xvfb-run -a {$venvPython} scheduler.py >> storage/logs/bot_runner.log 2>&1 &";
                exec($cmd, $output, $returnVar);
                Log::info("Bot exec triggered: {$cmd} | Exit Code: {$returnVar}");
            }

            $msg = "Berhasil memicu eksekusi " . count($jsonExport) . " item PENDING! Bot Playwright sedang memproses di latar belakang.";

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'count' => count($jsonExport),
                ]);
            }

            return redirect()->back()->with('success', $msg);

        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menjalankan bot: ' . $e->getMessage(),
                ], 500);
            }
            return redirect()->back()->with('error', 'Gagal menjalankan bot: ' . $e->getMessage());
        }
    }

    public function executionProgress()
    {
        $pendingCount = Schedule::where('status', 'pending')->count();
        $completedCount = Schedule::where('status', 'completed')->count();
        $failedCount = Schedule::where('status', 'failed')->count();
        $totalCount = Schedule::count();

        $latestUpdated = Schedule::orderBy('updated_at', 'desc')->first();
        $latestFailed = Schedule::where('status', 'failed')->orderBy('updated_at', 'desc')->first();

        return response()->json([
            'pending' => $pendingCount,
            'completed' => $completedCount,
            'failed' => $failedCount,
            'total' => $totalCount,
            'latest_item' => $latestUpdated ? $latestUpdated->item_code : null,
            'latest_note' => $latestUpdated ? $latestUpdated->notes : null,
            'failed_item' => $latestFailed ? $latestFailed->item_code : null,
            'failed_note' => $latestFailed ? $latestFailed->notes : null,
            'updated_at' => $latestUpdated ? $latestUpdated->updated_at->diffForHumans() : null,
        ]);
    }
}
