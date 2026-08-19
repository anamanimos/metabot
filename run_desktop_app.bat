@echo off
title Meta Story Auto Scheduler - Windows Desktop Application
color 0b
cd /d "%~dp0"

echo ============================================================================
echo 🚀 MEMULAI META STORY AUTO SCHEDULER (WINDOWS DESKTOP APPLICATION)
echo ============================================================================
echo.
echo  [1] Membuka Server Lokal Web UI...
echo  [2] Mendukung Passkey / Windows Hello / Fingerprint Otentikasi Meta
echo  [3] Profil Sesi Persisten Lokal Terintegrasi (100% Bebas Blokir Facebook)
echo.
echo ============================================================================
echo.

:: Cek apakah laragon atau python venv tersedia
if exist "venv\Scripts\python.exe" (
    set "PYTHON_BIN=venv\Scripts\python.exe"
) else (
    set "PYTHON_BIN=python"
)

:: Jalankan server lokal Laravel di background port 8000 jika belum berjalan
netstat -ano | findstr :8000 >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [INFO] Menjalankan server lokal Web UI di http://localhost:8000 ...
    start /min php artisan serve --port=8000
    timeout /t 3 /nobreak >nul
)

:: Buka Web UI di browser desktop bawaan
echo [INFO] Membuka Web UI Desktop App...
start http://localhost:8000/meta-accounts

echo.
echo ============================================================================
echo ✅ APLIKASI DESKTOP BERHASIL BERJALAN!
echo 💡 Jangan tutup jendela CMD ini selama Anda menggunakan aplikasi.
echo ============================================================================
echo.
pause
