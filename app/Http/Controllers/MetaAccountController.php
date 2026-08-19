<?php

namespace App\Http\Controllers;

use App\Models\MetaAccount;
use App\Models\Portfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MetaAccountController extends Controller
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
        $accounts = MetaAccount::withCount(['projects', 'portfolios'])->latest()->get();
        
        if ($accounts->isEmpty()) {
            MetaAccount::create([
                'account_name' => 'Akun Utama (Sevencols)',
                'session_folder' => 'user_data',
                'status' => 'login_required',
            ]);
            $accounts = MetaAccount::withCount(['projects', 'portfolios'])->latest()->get();
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['accounts' => $accounts]);
        }

        return view('meta_accounts.index', compact('accounts'));
    }

    /**
     * Halaman Detail Akun Meta (Menampilkan Portofolio & Project Terikat)
     */
    public function show($id)
    {
        $account = MetaAccount::with(['portfolios', 'projects' => function ($q) {
            $q->withCount('mediaFiles');
        }])->findOrFail($id);

        return view('meta_accounts.show', compact('account'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'account_name' => 'required|string|max:255',
            ]);

            $folderName = 'user_data_' . Str::slug($request->account_name, '_') . '_' . time();

            $account = MetaAccount::create([
                'account_name' => trim($request->account_name),
                'session_folder' => $folderName,
                'status' => 'login_required',
            ]);

            $msg = "Akun Meta '{$account->account_name}' berhasil dibuat!";

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'account' => $account,
                ]);
            }

            return redirect()->back()->with('success', $msg);

        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menambah akun: ' . $e->getMessage(),
                ], 500);
            }
            return redirect()->back()->with('error', 'Gagal menambah akun: ' . $e->getMessage());
        }
    }

    /**
     * Memindai & Mengambil Portofolio Khusus untuk Akun Ini
     */
    public function fetchPortfolios(Request $request, $id)
    {
        try {
            $account = MetaAccount::findOrFail($id);

            $confirmedPortfolios = [
                [
                    'portfolio_name' => 'Sevencols',
                    'asset_name' => 'Sevencols, sevencols',
                    'asset_type' => 'Halaman Facebook, profil Instagram',
                    'combined_target' => 'Sevencols - Sevencols, sevencols',
                ],
                [
                    'portfolio_name' => 'Sevencols',
                    'asset_name' => 'Arema Style, arema_style',
                    'asset_type' => 'Halaman Facebook, profil Instagram',
                    'combined_target' => 'Sevencols - Arema Style, arema_style',
                ],
                [
                    'portfolio_name' => 'Bikin Seragam Kota Malang',
                    'asset_name' => 'Bikin Seragam Kota Malang',
                    'asset_type' => 'Halaman Facebook',
                    'combined_target' => 'Bikin Seragam Kota Malang - Bikin Seragam Kota Malang',
                ],
                [
                    'portfolio_name' => 'Mahasiswa Malang',
                    'asset_name' => 'Mahasiswa Malang',
                    'asset_type' => 'Halaman Facebook',
                    'combined_target' => 'Mahasiswa Malang - Mahasiswa Malang',
                ]
            ];

            foreach ($confirmedPortfolios as $item) {
                Portfolio::updateOrCreate(
                    [
                        'meta_account_id' => $account->id,
                        'combined_target' => $item['combined_target']
                    ],
                    [
                        'name' => $item['combined_target'],
                        'portfolio_name' => $item['portfolio_name'],
                        'asset_name' => $item['asset_name'],
                        'asset_type' => $item['asset_type'],
                    ]
                );
            }

            $basePath = base_path();
            $pythonBin = $this->getPythonBinary();
            
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "start \"Memindai Aset Meta\" cmd /c \"cd /d \"{$basePath}\" && {$pythonBin} fetch_portfolios.py --user_data={$account->session_folder}\"";
                pclose(popen($cmd, "r"));
            } else {
                $venvPython = file_exists(base_path('venv/bin/python3')) ? base_path('venv/bin/python3') : 'python3';
                $cmd = "cd \"{$basePath}\" && export PLAYWRIGHT_BROWSERS_PATH=/var/www/meta.damaijaya.my.id/ms-playwright && xvfb-run -a {$venvPython} fetch_portfolios.py --user_data={$account->session_folder} >> storage/logs/bot_runner.log 2>&1 &";
                exec($cmd);
            }

            $count = $account->portfolios()->count();
            $msg = "Berhasil memicu pemindaian Aset Meta khusus akun '{$account->account_name}'! Total {$count} Aset Bisnis terhubung.";

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'count' => $count
                ]);
            }

            return redirect()->back()->with('success', $msg);

        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memindai portofolio: ' . $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', 'Gagal memindai portofolio: ' . $e->getMessage());
        }
    }

    /**
     * Membuka Jendela Browser Chromium Visual di Layar Windows untuk Login Manual & Passkey
     */
    public function openBrowserLogin(Request $request, $id)
    {
        try {
            set_time_limit(360);
            $account = MetaAccount::findOrFail($id);
            $basePath = base_path();
            $pythonBin = $this->getPythonBinary();

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "start \"Login Meta Visual Browser\" cmd /c \"cd /d \"{$basePath}\" && {$pythonBin} open_browser_login.py --user_data={$account->session_folder}\"";
                pclose(popen($cmd, "r"));
                $msg = "Jendela browser Chromium visual telah dibuka! Silakan lakukan login di browser tersebut; jendela browser dan CMD akan tertutup otomatis setelah login selesai.";
                $success = true;
            } else {
                $venvPython = file_exists(base_path('venv/bin/python3')) ? base_path('venv/bin/python3') : 'python3';
                $cmd = "export PLAYWRIGHT_BROWSERS_PATH=/var/www/meta.damaijaya.my.id/ms-playwright && cd \"{$basePath}\" && {$venvPython} open_browser_login.py --user_data={$account->session_folder}";
                $output = shell_exec($cmd);
                
                $jsonResult = null;
                if ($output) {
                    $lines = array_filter(array_map('trim', explode("\n", $output)));
                    foreach (array_reverse($lines) as $line) {
                        if (str_starts_with($line, '{') && str_ends_with($line, '}')) {
                            $jsonResult = @json_decode($line, true);
                            if ($jsonResult) break;
                        }
                    }
                }
                $success = $jsonResult['success'] ?? false;
                $msg = $jsonResult['message'] ?? ($success ? "Berhasil Login!" : "Proses Selesai.");
            }

            if ($success) {
                $account->status = 'active';
                $account->save();
            }

            return response()->json([
                'success' => true,
                'status' => $account->status,
                'message' => $msg
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal membuka browser: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Memeriksa Status Login Akun Meta dengan Tangkapan Layar (Screenshot) Live & Menyimpan Status Persisten ke DB
     */
    public function checkStatus($id)
    {
        try {
            set_time_limit(120);
            $account = MetaAccount::findOrFail($id);
            $basePath = base_path();
            $pythonBin = $this->getPythonBinary();

            $outputPath = "public/storage/previews/meta_account_{$account->id}.png";
            $resultJsonFile = storage_path("app/meta_check_result.json");
            if (file_exists($resultJsonFile)) {
                @unlink($resultJsonFile);
            }

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "cd /d \"{$basePath}\" && {$pythonBin} check_meta_login.py --user_data={$account->session_folder} --output={$outputPath}";
            } else {
                $venvPython = file_exists(base_path('venv/bin/python3')) ? base_path('venv/bin/python3') : 'python3';
                $cmd = "export PLAYWRIGHT_BROWSERS_PATH=/var/www/meta.damaijaya.my.id/ms-playwright && cd \"{$basePath}\" && {$venvPython} check_meta_login.py --user_data={$account->session_folder} --output={$outputPath}";
            }

            $output = shell_exec($cmd);
            
            $jsonResult = null;
            if (file_exists($resultJsonFile)) {
                $content = file_get_contents($resultJsonFile);
                $jsonResult = @json_decode($content, true);
            }

            if (!$jsonResult && $output) {
                $lines = array_filter(array_map('trim', explode("\n", $output)));
                foreach (array_reverse($lines) as $line) {
                    if (str_starts_with($line, '{') && str_ends_with($line, '}')) {
                        $jsonResult = @json_decode($line, true);
                        if ($jsonResult) break;
                    }
                }
            }

            $isLoggedIn = $jsonResult['logged_in'] ?? false;
            $screenshotUrl = $jsonResult['screenshot'] ?? null;
            if ($screenshotUrl) {
                $screenshotUrl = asset($screenshotUrl);
            }
            $msg = $jsonResult['message'] ?? ($isLoggedIn ? "Akun Terhubung!" : "Belum Login");

            $account->status = $isLoggedIn ? 'active' : 'login_required';
            $account->save();

            return response()->json([
                'success' => true,
                'is_logged_in' => $isLoggedIn,
                'status' => $account->status,
                'screenshot_url' => $screenshotUrl,
                'message' => $msg
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengecek status: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Melakukan Login Langsung Meta / Facebook dari Form Web UI
     */
    public function directLogin(Request $request, $id)
    {
        try {
            set_time_limit(180);
            $request->validate([
                'email' => 'required|string',
                'password' => 'required|string',
                'two_factor' => 'nullable|string',
            ]);

            $account = MetaAccount::findOrFail($id);
            $basePath = base_path();
            $pythonBin = $this->getPythonBinary();

            $emailArg = escapeshellarg($request->email);
            $passArg = escapeshellarg($request->password);
            $twoFactorArg = $request->two_factor ? escapeshellarg($request->two_factor) : '';

            $cmdExtra = $twoFactorArg ? "--two_factor={$twoFactorArg}" : "";

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "cd /d \"{$basePath}\" && {$pythonBin} login_meta_account.py --email={$emailArg} --password={$passArg} {$cmdExtra} --user_data={$account->session_folder}";
            } else {
                $venvPython = file_exists(base_path('venv/bin/python3')) ? base_path('venv/bin/python3') : 'python3';
                $cmd = "export PLAYWRIGHT_BROWSERS_PATH=/var/www/meta.damaijaya.my.id/ms-playwright && cd \"{$basePath}\" && {$pythonBin} login_meta_account.py --email={$emailArg} --password={$passArg} {$cmdExtra} --user_data={$account->session_folder}";
            }

            $output = shell_exec($cmd);

            $jsonResult = null;
            if ($output) {
                $lines = array_filter(array_map('trim', explode("\n", $output)));
                foreach (array_reverse($lines) as $line) {
                    if (str_starts_with($line, '{') && str_ends_with($line, '}')) {
                        $jsonResult = @json_decode($line, true);
                        if ($jsonResult) break;
                    }
                }
            }

            $success = $jsonResult['success'] ?? false;
            $msg = $jsonResult['message'] ?? ($success ? "Berhasil Login ke Meta!" : "Gagal Login ke Meta.");

            if ($success) {
                $account->status = 'active';
                $account->save();
            }

            return response()->json([
                'success' => $success,
                'status' => $account->status,
                'message' => $msg
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal login: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mengimpor Berkas Sesi state.json / Cookie JSON & Menyimpan Status Persisten ke DB
     */
    public function importState(Request $request, $id)
    {
        try {
            $account = MetaAccount::findOrFail($id);
            
            $jsonContent = null;
            if ($request->hasFile('state_file')) {
                $jsonContent = file_get_contents($request->file('state_file')->getRealPath());
            } elseif ($request->filled('state_json')) {
                $jsonContent = $request->state_json;
            }

            if (!$jsonContent) {
                return response()->json(['success' => false, 'message' => 'Silakan unggah file state.json atau tempel teks JSON cookie!'], 400);
            }

            $parsed = @json_decode($jsonContent, true);
            if (!is_array($parsed)) {
                return response()->json(['success' => false, 'message' => 'Format JSON cookie tidak valid! Harus berupa objek JSON state Playwright.'], 400);
            }

            if (isset($parsed[0]) && is_array($parsed[0])) {
                $parsed = [
                    'cookies' => $parsed,
                    'origins' => []
                ];
            }

            $statePath = base_path('state.json');
            if (file_exists($statePath)) {
                @chmod($statePath, 0666);
            }

            @file_put_contents($statePath, json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            if (file_exists($statePath)) {
                @chmod($statePath, 0666);
            }

            $account->status = 'active';
            $account->save();

            return response()->json([
                'success' => true,
                'message' => "Sesi Otentikasi Sempurna untuk '{$account->account_name}' berhasil di-impor & terhubung!",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor sesi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menghapus Akun Meta & Cleaning Folder Sesi jika ada
     */
    public function destroy(Request $request, $id)
    {
        try {
            $account = MetaAccount::findOrFail($id);
            $accountName = $account->account_name;
            $sessionFolder = $account->session_folder;

            if ($sessionFolder !== 'user_data') {
                $folderPath = base_path($sessionFolder);
                if (file_exists($folderPath)) {
                    $this->deleteDirectory($folderPath);
                }
            }

            $account->delete();

            $msg = "Akun Meta '{$accountName}' berhasil dihapus!";

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
                    'message' => 'Gagal menghapus akun: ' . $e->getMessage(),
                ], 500);
            }
            return redirect()->back()->with('error', 'Gagal menghapus akun: ' . $e->getMessage());
        }
    }

    /**
     * Helper rekursif hapus direktori
     */
    protected function deleteDirectory($dirPath): bool
    {
        if (!is_dir($dirPath)) {
            return false;
        }
        $files = array_diff(scandir($dirPath), array('.', '..'));
        foreach ($files as $file) {
            $filePath = $dirPath . DIRECTORY_SEPARATOR . $file;
            (is_dir($filePath)) ? $this->deleteDirectory($filePath) : @unlink($filePath);
        }
        return @rmdir($dirPath);
    }
}
