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
                'status' => 'active',
            ]);
            $accounts = MetaAccount::withCount(['projects', 'portfolios'])->latest()->get();
        }

        // Cek secara cepat ketersediaan cookie sesi state.json untuk setiap akun
        $stateFile = base_path('state.json');
        $hasStateCookie = false;
        if (file_exists($stateFile)) {
            $content = @file_get_contents($stateFile);
            if ($content && str_contains($content, 'c_user')) {
                $hasStateCookie = true;
            }
        }

        foreach ($accounts as $acc) {
            if ($hasStateCookie && $acc->status !== 'active') {
                $acc->status = 'active';
                $acc->save();
            }
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['accounts' => $accounts]);
        }

        return view('meta_accounts.index', compact('accounts', 'hasStateCookie'));
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

            $basePath = base_path();
            $pythonBin = $this->getPythonBinary();
            
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "start \"Login Akun Meta Baru\" cmd /k \"cd /d \"{$basePath}\" && {$pythonBin} fetch_portfolios.py --user_data={$folderName}\"";
                pclose(popen($cmd, "r"));
            } else {
                exec("{$pythonBin} {$basePath}/fetch_portfolios.py --user_data={$folderName} &");
            }

            $msg = "Akun Meta '{$account->account_name}' berhasil dibuat! Jendela bot untuk login telah disiapkan.";

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
     * Memeriksa Status Login Akun Meta
     */
    public function checkStatus($id)
    {
        $account = MetaAccount::findOrFail($id);
        $stateFile = base_path('state.json');

        $isLoggedIn = false;
        $fbUserId = null;

        if (file_exists($stateFile)) {
            $content = @file_get_contents($stateFile);
            $json = @json_decode($content, true);
            if (isset($json['cookies']) && is_array($json['cookies'])) {
                foreach ($json['cookies'] as $cookie) {
                    if (isset($cookie['name']) && $cookie['name'] === 'c_user') {
                        $isLoggedIn = true;
                        $fbUserId = $cookie['value'] ?? null;
                        break;
                    }
                }
            }
        }

        $account->status = $isLoggedIn ? 'active' : 'login_required';
        $account->save();

        $msg = $isLoggedIn 
            ? "Status Akun '{$account->account_name}' TERHUBUNG! (ID Pengguna Facebook: {$fbUserId})" 
            : "Status Akun '{$account->account_name}' BELUM LOGIN. Silakan import file state.json atau hubungkan sesi terlebih dahulu.";

        return response()->json([
            'success' => true,
            'is_logged_in' => $isLoggedIn,
            'fb_user_id' => $fbUserId,
            'status' => $account->status,
            'message' => $msg
        ]);
    }

    /**
     * Mengimpor Berkas Sesi state.json / Cookie JSON
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

            // Simpan ke state.json
            file_put_contents(base_path('state.json'), json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $account->status = 'active';
            $account->save();

            return response()->json([
                'success' => true,
                'message' => "Sesi Sembungan untuk '{$account->account_name}' berhasil di-impor & terhubung!",
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
