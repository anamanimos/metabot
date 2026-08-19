@echo off
title Asisten Login Meta Business Suite & Export state.json
cd /d "%~dp0"
echo Memulai Asisten Login Meta Business Suite...
venv\Scripts\python.exe export_session.py
if %ERRORLEVEL% NEQ 0 (
    python export_session.py
)
pause
