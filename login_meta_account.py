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

# Set shared Playwright browser path
shared_browser_dir = Path("/var/www/meta.damaijaya.my.id/ms-playwright")
if shared_browser_dir.exists():
    os.environ["PLAYWRIGHT_BROWSERS_PATH"] = str(shared_browser_dir)

from playwright.sync_api import sync_playwright, TimeoutError as PlaywrightTimeoutError

def perform_meta_login():
    parser = argparse.ArgumentParser(description="Automated Meta / Facebook Web Login")
    parser.add_argument("--email", type=str, required=True, help="Facebook Email / Phone")
    parser.add_argument("--password", type=str, required=True, help="Facebook Password")
    parser.add_argument("--two_factor", type=str, default=None, help="2FA Authentication Code (Optional)")
    parser.add_argument("--user_data", type=str, default="./user_data", help="Session directory")
    args, _ = parser.parse_known_args()

    user_data_dir = str(Path(args.user_data).resolve())

    is_headless = True if sys.platform != 'win32' else False

    result = {
        "success": False,
        "message": "",
        "url": ""
    }

    try:
        with sync_playwright() as p:
            context = p.chromium.launch_persistent_context(
                user_data_dir=user_data_dir,
                headless=is_headless,
                viewport={"width": 1280, "height": 850},
                args=[
                    "--disable-blink-features=AutomationControlled",
                    "--no-sandbox",
                    "--disable-dev-shm-usage",
                    "--disable-gpu",
                    "--disable-software-rasterizer"
                ]
            )

            page = context.pages[0] if context.pages else context.new_page()

            # Navigasi ke Halaman Login Facebook
            print("Navigasi ke Halaman Login Facebook...", file=sys.stderr)
            page.goto("https://www.facebook.com/login", wait_until="networkidle")
            time.sleep(2)

            # Isi Email & Password
            if page.locator("input[name='email']").is_visible(timeout=5000):
                page.fill("input[name='email']", args.email)
                page.fill("input[name='pass']", args.password)
                time.sleep(1)

                login_btn = page.locator("button[name='login'], button[type='submit'], input[type='submit']").first
                login_btn.click()
                print("Tombol Login diklik. Menunggu otentikasi Facebook...", file=sys.stderr)
                time.sleep(6)

            current_url = page.url
            page_content = page.content().lower()

            # Cek jika butuh 2FA
            if ("two_factor" in current_url.lower() or "approvals_code" in page_content or "2fa" in page_content) and args.two_factor:
                print("Deteksi Verifikasi 2FA! Mengisikan kode 2FA...", file=sys.stderr)
                two_factor_inputs = page.locator("input[name='approvals_code'], input[type='text']").all()
                if two_factor_inputs:
                    two_factor_inputs[0].fill(args.two_factor)
                    time.sleep(1)
                    submit_2fa = page.locator("button[type='submit'], button:has-text('Continue'), button:has-text('Lanjutkan')").first
                    if submit_2fa.is_visible():
                        submit_2fa.click()
                        time.sleep(6)

            # Buka Meta Business Suite untuk mengonfirmasi sesi bisnis
            print("Membuka Meta Business Suite untuk memverifikasi otentikasi...", file=sys.stderr)
            page.goto("https://business.facebook.com/latest/home", wait_until="domcontentloaded")
            time.sleep(4)

            final_url = page.url
            result["url"] = final_url

            if "login" not in final_url.lower() and "business.facebook.com" in final_url:
                result["success"] = True
                result["message"] = "Berhasil Login ke Meta Business Suite & Sesi Otentikasi Terverifikasi Aktif!"

                # Export state.json persisten untuk kompatibilitas OS-Agnostic
                state = context.storage_state()
                state_file = Path("state.json")
                with open(state_file, "w", encoding="utf-8") as sf:
                    json.dump(state, sf, indent=2, ensure_ascii=False)
                print(f"Berkas state.json berhasil disimpan ke: {state_file.resolve()}", file=sys.stderr)

            elif "approvals_code" in page.content().lower() or "two_factor" in final_url.lower():
                result["success"] = False
                result["message"] = "Akun membutuhkan Kode Otentikasi Dua-Faktor (2FA). Silakan masukkan kode 2FA Anda."
            else:
                result["success"] = False
                result["message"] = "Gagal Login: Email/Password tidak cocok atau Facebook meminta konfirmasi keamanan tambahan."

            context.close()

    except Exception as e:
        result["success"] = False
        result["message"] = f"Kesalahan proses login: {str(e)}"

    return result

if __name__ == "__main__":
    res = perform_meta_login()
    print(json.dumps(res))
