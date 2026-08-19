<?php

namespace App\Http\Controllers;

use App\Models\MetaAccount;
use App\Models\Portfolio;
use Illuminate\Http\Request;

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
     * Menjalankan script Python fetch_portfolios.py untuk memindai Aset Bisnis terikat Akun Meta spesifik
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

            // Load & Sync data 2-tingkat Aset Bisnis terikat Akun Meta ke Database MySQL
            if (file_exists($jsonFile)) {
                $content = json_decode(file_get_contents($jsonFile), true);
                if (is_array($content) && count($content) > 0 && $account) {
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
                                ]
                            );
                        }
                    }
                }
            }

            $accountPortfolios = Portfolio::where('meta_account_id', $account?->id)->orderBy('portfolio_name')->get();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Aset Bisnis 2-Tingkat untuk Akun Meta '{$account?->account_name}' berhasil dipindai dan diperbarui!",
                    'portfolios' => $accountPortfolios,
                ]);
            }

            return redirect()->back()->with('success', 'Daftar Aset Bisnis berhasil dipindai.');
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
