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

            $basePath = base_path();
            $pythonBin = $this->getPythonBinary();
            
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "start \"Login Akun Meta Baru\" cmd /k \"cd /d \"{$basePath}\" && {$pythonBin} fetch_portfolios.py --user_data={$folderName}\"";
                pclose(popen($cmd, "r"));
            } else {
                exec("{$pythonBin} {$basePath}/fetch_portfolios.py --user_data={$folderName} &");
            }

            $msg = "Akun Meta '{$account->account_name}' berhasil dibuat! Jendela CMD bot untuk login 1-kali telah dibuka.";

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
     * Menghapus Akun Meta & Cleaning Folder Sesi jika ada
     */
    public function destroy(Request $request, $id)
    {
        try {
            $account = MetaAccount::findOrFail($id);
            $accountName = $account->account_name;
            $sessionFolder = $account->session_folder;

            // Hapus folder sesi dari disk jika bukan folder user_data default
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
