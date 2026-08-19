import sys
import os
import io
import json
import time
import argparse
from pathlib import Path

# Fix Windows Apache CGI / PHP shell_exec invalid handle issue [WinError 6]
if sys.platform == 'win32':
    for stream_name in ('stdout', 'stderr'):
        try:
            stream = getattr(sys, stream_name)
            if stream is None:
                setattr(sys, stream_name, open(os.devnull, 'w', encoding='utf-8'))
            else:
                stream.fileno()
        except Exception:
            try:
                setattr(sys, stream_name, open(os.devnull, 'w', encoding='utf-8'))
            except Exception:
                setattr(sys, stream_name, io.StringIO())

# Set shared Playwright browser path if available
shared_browser_dir = Path("/var/www/meta.damaijaya.my.id/ms-playwright")
if shared_browser_dir.exists():
    os.environ["PLAYWRIGHT_BROWSERS_PATH"] = str(shared_browser_dir)

from playwright.sync_api import sync_playwright

def open_visual_browser():
    parser = argparse.ArgumentParser()
    parser.add_argument("--user_data", type=str, default="./user_data")
    args, _ = parser.parse_known_args()

    user_data_dir = str(Path(args.user_data).resolve())

    result = {
        "success": False,
        "message": ""
    }

    try:
        with sync_playwright() as p:
            try:
                print("Membuka jendela browser Chromium visual di layar Windows...", file=sys.stderr)
            except Exception:
                pass
            
            launch_args = {
                "user_data_dir": user_data_dir,
                "headless": False,
                "viewport": {"width": 1280, "height": 850},
                "args": [
                    "--start-maximized",
                    "--disable-blink-features=AutomationControlled"
                ]
            }

            if sys.platform == 'win32':
                try:
                    context = p.chromium.launch_persistent_context(channel="chrome", **launch_args)
                except Exception:
                    context = p.chromium.launch_persistent_context(**launch_args)
            else:
                context = p.chromium.launch_persistent_context(**launch_args)

            # Impor cookie dari state.json jika ada
            state_file = Path("state.json")
            if state_file.exists():
                try:
                    with open(state_file, "r", encoding="utf-8") as sf:
                        st = json.load(sf)
                        if isinstance(st, dict) and "cookies" in st:
                            context.add_cookies(st["cookies"])
                except Exception:
                    pass

            page = context.pages[0] if context.pages else context.new_page()
            page.goto("https://business.facebook.com/latest/home")

            try:
                print("Menunggu pengguna melakukan login & menyelesaikan 2FA di jendela browser...", file=sys.stderr)
            except Exception:
                pass

            is_logged_in = False
            for _ in range(300):
                try:
                    time.sleep(2)
                    current_url = page.url.lower()

                    # Stripping query params (sesudah '?') agar biz_login_source tidak dikira halaman login
                    url_path = current_url.split("?")[0]

                    is_in_login_path = (
                        "facebook.com/login" in url_path or
                        "/login" in url_path or
                        "/loginpage" in url_path or
                        "/checkpoint" in url_path or
                        "/two_factor" in url_path or
                        "/two_step" in url_path or
                        "/auth" in url_path or
                        "/identity" in url_path
                    )

                    has_2fa_input = False
                    try:
                        if page.locator("input[name='approvals_code']").count() > 0 or \
                           page.locator("input[type='text'][autocomplete='one-time-code']").count() > 0 or \
                           page.locator("text='Kode Otentikasi'").count() > 0 or \
                           page.locator("text='Authentication Code'").count() > 0 or \
                           page.locator("text='Passkey'").count() > 0:
                            has_2fa_input = True
                    except Exception:
                        pass

                    has_dashboard_element = False
                    try:
                        if page.locator("text='Pengelola Iklan'").count() > 0 or \
                           page.locator("text='Kotak Masuk'").count() > 0 or \
                           page.locator("text='Beranda'").count() > 0 or \
                           page.locator("text='Notifikasi'").count() > 0 or \
                           page.locator("text='Monetisasi'").count() > 0:
                            has_dashboard_element = True
                    except Exception:
                        pass

                    # Jika elemen dashboard sudah aktif DAN tidak di halaman login/2FA
                    if (has_dashboard_element or ("business.facebook.com/latest/home" in url_path)) and not is_in_login_path and not has_2fa_input:
                        time.sleep(2)
                        is_logged_in = True
                        break
                except Exception:
                    break

            if is_logged_in:
                result["success"] = True
                result["message"] = "Login & Verifikasi 2FA Meta Selesai 100%! Sesi otentikasi telah disimpan secara permanen ke state.json."

                state = context.storage_state()
                with open(state_file, "w", encoding="utf-8") as sf:
                    json.dump(state, sf, indent=2, ensure_ascii=False)

                try:
                    page.evaluate("alert('✅ LOGIN META BERHASIL 100%! Jendela akan tertutup otomatis dalam 2 detik.')")
                    time.sleep(2)
                except Exception:
                    pass
            else:
                result["success"] = False
                result["message"] = "Waktu login/2FA habis atau jendela browser ditutup sebelum verifikasi selesai."

            context.close()

    except Exception as e:
        result["success"] = False
        result["message"] = f"Proses login selesai: {str(e)}"

    return result

if __name__ == "__main__":
    res = open_visual_browser()
    
    try:
        os.makedirs("storage/app", exist_ok=True)
        with open("storage/app/meta_login_result.json", "w", encoding="utf-8") as f:
            json.dump(res, f, indent=2, ensure_ascii=False)
    except Exception:
        pass

    try:
        sys.__stdout__.write(json.dumps(res) + "\n")
    except Exception:
        pass
