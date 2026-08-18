<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use App\Models\MetaAccount;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
        return 'python';
    }

    /**
     * Menampilkan Dashboard Web UI utama
     */
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

    /**
     * Menghapus item jadwal
     */
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
     * Ekspor jadwal PENDING ke schedule.json dan MEMBUKA JENDELA CMD TERLIHAT di Windows Desktop
     */
    public function syncAndRunBot(Request $request)
    {
        $pendingSchedules = Schedule::with(['mediaFile', 'project'])
            ->where('status', 'pending')
            ->orderBy('target_date')
            ->orderBy('target_time')
            ->get();

        if ($pendingSchedules->isEmpty()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada jadwal dengan status PENDING untuk dieksekusi.',
                ], 422);
            }
            return redirect()->back()->with('error', 'Tidak ada jadwal dengan status PENDING untuk dieksekusi.');
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
        file_put_contents($scheduleJsonPath, json_encode($jsonExport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $basePath = base_path();
        $pythonBin = $this->getPythonBinary();
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = "start \"Meta Story Auto Scheduler Bot\" cmd /k \"cd /d \"{$basePath}\" && {$pythonBin} scheduler.py\"";
            pclose(popen($cmd, "r"));
        } else {
            exec("{$pythonBin} {$basePath}/scheduler.py &");
        }

        $msg = "Berhasil mengekspor " . count($jsonExport) . " item ke schedule.json dan membuka Jendela CMD Bot di desktop!";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'count' => count($jsonExport),
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }
}
