import os
import sys
import io
import json
import time
import argparse
from pathlib import Path

# Force UTF-8 output safely on Windows CMD / PHP CGI without WinError 6
if sys.platform == 'win32':
    try:
        if hasattr(sys.stdout, 'buffer') and sys.stdout.buffer is not None:
            sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
    except Exception:
        pass
    try:
        if hasattr(sys.stderr, 'buffer') and sys.stderr.buffer is not None:
            sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')
    except Exception:
        pass

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
                print("Menunggu pengguna melakukan login di jendela browser ini...", file=sys.stderr)
            except Exception:
                pass

            is_logged_in = False
            for _ in range(300):
                try:
                    time.sleep(2)
                    current_url = page.url
                    
                    has_dashboard_element = False
                    try:
                        if page.locator("text='Beranda'").count() > 0 or \
                           page.locator("text='Home'").count() > 0 or \
                           page.locator("text='Notifikasi'").count() > 0 or \
                           page.locator("text='Pengelola Iklan'").count() > 0 or \
                           page.locator("text='Konten'").count() > 0:
                            has_dashboard_element = True
                    except Exception:
                        pass

                    if (has_dashboard_element or "business.facebook.com/latest/home" in current_url) and "facebook.com/login" not in current_url:
                        is_logged_in = True
                        break
                except Exception:
                    break

            if is_logged_in:
                result["success"] = True
                result["message"] = "Login Meta Berhasil! Sesi otentikasi telah disimpan secara permanen ke state.json."

                state = context.storage_state()
                with open(state_file, "w", encoding="utf-8") as sf:
                    json.dump(state, sf, indent=2, ensure_ascii=False)

                try:
                    page.evaluate("alert('✅ LOGIN META BERHASIL! Sesi otentikasi telah disimpan secara otomatis.')")
                    time.sleep(2)
                except Exception:
                    pass
            else:
                result["success"] = False
                result["message"] = "Waktu login habis atau jendela browser ditutup sebelum login selesai."

            context.close()

    except Exception as e:
        result["success"] = False
        result["message"] = f"Proses login selesai: {str(e)}"

    return result

if __name__ == "__main__":
    res = open_visual_browser()
    print(json.dumps(res))
