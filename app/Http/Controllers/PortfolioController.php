<?php

namespace App\Http\Controllers;

use App\Models\MetaAccount;
use App\Models\Portfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PortfolioController extends Controller
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
     * Menjalankan script Python fetch_portfolios.py untuk memindai Aset Bisnis terikat Akun Meta spesifik dengan Full Sync Soft-Deactivation
     */
    public function fetchFromMeta(Request $request)
    {
        try {
            $basePath = base_path();
            $jsonFile = base_path('portfolios.json');
            $pythonBin = $this->getPythonBinary();

            $metaAccountId = $request->input('meta_account_id');
            $account = MetaAccount::find($metaAccountId) ?? MetaAccount::first();

            $sessionFolder = $account ? $account->session_folder : 'user_data';
            $initialMtime = file_exists($jsonFile) ? filemtime($jsonFile) : 0;

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "start \"Fetch Aset Bisnis Meta\" cmd /c \"cd /d \"{$basePath}\" && {$pythonBin} fetch_portfolios.py --user_data={$sessionFolder}\"";
                pclose(popen($cmd, "r"));
            } else {
                exec("{$pythonBin} {$basePath}/fetch_portfolios.py --user_data={$sessionFolder} &");
            }

            $startWait = time();
            while ((time() - $startWait) < 15) {
                sleep(1);
                if (file_exists($jsonFile) && filemtime($jsonFile) > $initialMtime) {
                    break;
                }
            }

            $deactivatedCount = 0;
            $syncedCombinedTargets = [];

            // Load & Sync data 2-tingkat Aset Bisnis terikat Akun Meta ke Database MySQL
            if (file_exists($jsonFile)) {
                $content = json_decode(file_get_contents($jsonFile), true);
                if (is_array($content) && count($content) > 0 && $account) {
                    $syncTimestamp = now();

                    DB::transaction(function () use ($content, $account, $syncTimestamp, &$syncedCombinedTargets, &$deactivatedCount) {
                        foreach ($content as $p) {
                            $combinedTarget = $p['combined_target'] ?? ($p['name'] ?? null);
                            if (!empty($combinedTarget)) {
                                Portfolio::updateOrCreate(
                                    [
                                        'meta_account_id' => $account->id,
                                        'combined_target' => $combinedTarget
                                    ],
                                    [
                                        'name' => $combinedTarget,
                                        'portfolio_name' => $p['portfolio_name'] ?? $p['name'] ?? null,
                                        'asset_name' => $p['asset_name'] ?? $p['name'] ?? null,
                                        'asset_type' => $p['asset_type'] ?? null,
                                        'is_active' => true,
                                        'last_synced_at' => $syncTimestamp,
                                    ]
                                );
                                $syncedCombinedTargets[] = $combinedTarget;
                            }
                        }

                        // Nonaktifkan (soft-delete) semua baris milik akun ini yang TIDAK muncul di hasil scan terbaru
                        $deactivatedCount = Portfolio::where('meta_account_id', $account->id)
                            ->whereNotIn('combined_target', $syncedCombinedTargets)
                            ->where('is_active', true)
                            ->update(['is_active' => false]);

                        Log::info("Sync portfolio akun {$account->id}: " . count($syncedCombinedTargets) . " aset aktif, {$deactivatedCount} aset dinonaktifkan.");
                    });
                }
            }

            $accountPortfolios = Portfolio::where('meta_account_id', $account?->id)
                ->where('is_active', true)
                ->orderBy('portfolio_name')
                ->get();

            $totalActive = count($syncedCombinedTargets);
            $msg = "Sinkronisasi portofolio selesai: {$totalActive} aset aktif terhubung" . 
                   ($deactivatedCount > 0 ? ", {$deactivatedCount} aset dinonaktifkan (tidak ditemukan lagi di Meta Business Suite)." : ".");

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'total_active' => $totalActive,
                    'deactivated_count' => $deactivatedCount,
                    'message' => $msg,
                    'portfolios' => $accountPortfolios,
                ]);
            }

            return redirect()->back()->with('success', $msg);
        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memindai aset bisnis: ' . $e->getMessage(),
                ], 500);
            }
            return redirect()->back()->with('error', 'Gagal memindai aset bisnis: ' . $e->getMessage());
        }
    }

    /**
     * Alias method untuk kompatibilitas route portfolios/fetch
     */
    public function fetchPortfolios(Request $request)
    {
        return $this->fetchFromMeta($request);
    }
}
