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

# Set shared Playwright browser path to /var/www/meta.damaijaya.my.id/ms-playwright if available
shared_browser_dir = Path("/var/www/meta.damaijaya.my.id/ms-playwright")
if shared_browser_dir.exists():
    os.environ["PLAYWRIGHT_BROWSERS_PATH"] = str(shared_browser_dir)

from playwright.sync_api import sync_playwright

def sanitize_cookies_for_playwright(raw_cookies):
    """Membersihkan format cookie dari Cookie-Editor agar 100% kompatibel dengan Playwright"""
    sanitized = []
    for c in raw_cookies:
        if not isinstance(c, dict):
            continue
        name = c.get("name")
        value = c.get("value")
        if not name or value is None:
            continue

        domain = c.get("domain", ".facebook.com")
        if domain.startswith("http://") or domain.startswith("https://"):
            domain = domain.split("//", 1)[1].split("/")[0]

        cookie = {
            "name": str(name),
            "value": str(value),
            "domain": domain,
            "path": c.get("path", "/")
        }

        same_site = str(c.get("sameSite", "")).lower()
        if "lax" in same_site:
            cookie["sameSite"] = "Lax"
        elif "strict" in same_site:
            cookie["sameSite"] = "Strict"
        elif "none" in same_site or "no_restriction" in same_site:
            cookie["sameSite"] = "None"

        if "httpOnly" in c:
            cookie["httpOnly"] = bool(c["httpOnly"])
        if "secure" in c:
            cookie["secure"] = bool(c["secure"])

        exp = c.get("expires") or c.get("expirationDate")
        if exp is not None:
            try:
                cookie["expires"] = float(exp)
            except (ValueError, TypeError):
                pass

        sanitized.append(cookie)
    return sanitized

def check_login():
    parser = argparse.ArgumentParser()
    parser.add_argument("--user_data", type=str, default="./user_data")
    parser.add_argument("--output", type=str, default="public/storage/previews/meta_login.png")
    args, _ = parser.parse_known_args()

    output_path = str(Path(args.output).resolve())
    os.makedirs(os.path.dirname(output_path), exist_ok=True)

    is_headless = True if sys.platform != 'win32' else False

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
            launch_args = {
                "headless": is_headless,
                "args": [
                    "--disable-blink-features=AutomationControlled",
                    "--no-sandbox",
                    "--disable-dev-shm-usage",
                    "--disable-gpu",
                    "--disable-software-rasterizer"
                ]
            }

            if sys.platform == 'win32':
                try:
                    browser = p.chromium.launch(channel="chrome", **launch_args)
                except Exception:
                    browser = p.chromium.launch(**launch_args)
            else:
                browser = p.chromium.launch(**launch_args)

            context = browser.new_context(viewport={"width": 1280, "height": 800})

            # Impor cookie otentikasi sesi dari state.json jika ada
            if storage_state_path and Path(storage_state_path).exists():
                try:
                    with open(storage_state_path, "r", encoding="utf-8") as sf:
                        state_data = json.load(sf)
                        raw_cookies = []
                        if isinstance(state_data, list):
                            raw_cookies = state_data
                        elif isinstance(state_data, dict) and "cookies" in state_data:
                            raw_cookies = state_data["cookies"]

                        if raw_cookies:
                            clean_cookies = sanitize_cookies_for_playwright(raw_cookies)
                            context.add_cookies(clean_cookies)
                except Exception as ex:
                    pass

            page = context.new_page()
            page.goto("https://business.facebook.com/latest/home", wait_until="domcontentloaded")
            time.sleep(5)

            current_url = page.url
            result["url"] = current_url

            page.screenshot(path=output_path, full_page=False)
            relative_screenshot = args.output.replace("public/", "/")
            if not relative_screenshot.startswith("/"):
                relative_screenshot = "/" + relative_screenshot
            result["screenshot"] = relative_screenshot

            # Deteksi elemen dashboard Meta Business Suite (Beranda / Notifikasi / Pengelola Iklan / Home)
            has_dashboard = False
            try:
                if page.locator("text='Beranda'").count() > 0 or \
                   page.locator("text='Home'").count() > 0 or \
                   page.locator("text='Notifikasi'").count() > 0 or \
                   page.locator("text='Pengelola Iklan'").count() > 0 or \
                   page.locator("text='Konten'").count() > 0:
                    has_dashboard = True
            except Exception:
                pass

            if (has_dashboard or "business.facebook.com/latest/home" in current_url) and "facebook.com/login" not in current_url:
                result["logged_in"] = True
                result["message"] = "Sesi Meta Business Suite Terverifikasi Aktif & Terhubung!"
            else:
                result["logged_in"] = False
                result["message"] = "Sesi belum ter-login ke Meta Business Suite (Dialihkan ke Halaman Login)."

            browser.close()

    except Exception as e:
        result["logged_in"] = False
        result["message"] = f"Gagal mengecek sesi Meta: {str(e)}"

    return result

if __name__ == "__main__":
    res = check_login()
    print(json.dumps(res))
