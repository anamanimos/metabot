import os
import sys
import io
import json
import time
import argparse
from pathlib import Path

# Force UTF-8 output
if sys.platform == 'win32':
    try:
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
        sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')
    except Exception:
        pass

# Set shared Playwright browser path to /var/www/meta.damaijaya.my.id/ms-playwright if available
shared_browser_dir = Path("/var/www/meta.damaijaya.my.id/ms-playwright")
if shared_browser_dir.exists():
    os.environ["PLAYWRIGHT_BROWSERS_PATH"] = str(shared_browser_dir)

from playwright.sync_api import sync_playwright

def check_login():
    parser = argparse.ArgumentParser()
    parser.add_argument("--user_data", type=str, default="./user_data")
    parser.add_argument("--output", type=str, default="public/storage/previews/meta_login.png")
    args, _ = parser.parse_known_args()

    user_data_dir = str(Path(args.user_data).resolve())
    output_path = str(Path(args.output).resolve())
    os.makedirs(os.path.dirname(output_path), exist_ok=True)

    is_headless = False if sys.platform == 'win32' and os.environ.get("DISPLAY") else (not bool(os.environ.get("DISPLAY")))
    if sys.platform != 'win32' and not os.environ.get("DISPLAY"):
        is_headless = True

    storage_state_file = Path("state.json")
    storage_state_path = str(storage_state_file) if storage_state_file.exists() else None

    result = {
        "logged_in": False,
        "url": "",
        "screenshot": "",
        "message": ""
    }

    try:
        with sync_playwright() as p:
            context = p.chromium.launch_persistent_context(
                user_data_dir=user_data_dir,
                headless=is_headless,
                viewport={"width": 1280, "height": 800},
                args=[
                    "--disable-blink-features=AutomationControlled",
                    "--no-sandbox",
                    "--disable-dev-shm-usage"
                ]
            )

            if storage_state_path:
                try:
                    with open(storage_state_path, "r", encoding="utf-8") as sf:
                        state_data = json.load(sf)
                        if "cookies" in state_data and state_data["cookies"]:
                            context.add_cookies(state_data["cookies"])
                except Exception:
                    pass

            page = context.pages[0] if context.pages else context.new_page()
            page.goto("https://business.facebook.com/latest/home", wait_until="networkidle")
            time.sleep(4)

            current_url = page.url
            result["url"] = current_url

            page.screenshot(path=output_path, full_page=False)
            relative_screenshot = args.output.replace("public/", "/")
            if not relative_screenshot.startswith("/"):
                relative_screenshot = "/" + relative_screenshot
            result["screenshot"] = relative_screenshot

            if "login" not in current_url.lower() and "business.facebook.com" in current_url:
                result["logged_in"] = True
                result["message"] = "Sesi Meta Business Suite Terverifikasi Aktif & Terhubung!"
            else:
                result["logged_in"] = False
                result["message"] = "Sesi belum ter-login ke Meta Business Suite (Dialihkan ke Halaman Login)."

            context.close()

    except Exception as e:
        result["logged_in"] = False
        result["message"] = f"Gagal memeriksa sesi Meta: {str(e)}"

    print(json.dumps(result))

if __name__ == "__main__":
    check_login()
