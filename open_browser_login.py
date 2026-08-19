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
            # Tunggu hingga 10 menit (300 iterasi x 2 detik = 600 detik) untuk memberi cukup waktu menyelesaikan 2FA
            for _ in range(300):
                try:
                    time.sleep(2)
                    current_url = page.url.lower()

                    # 1. Cek apakah masih dalam halaman login / 2FA / checkpoint / verifikasi keamanan
                    is_in_checkpoint_or_2fa = (
                        "checkpoint" in current_url or
                        "two_step" in current_url or
                        "two_factor" in current_url or
                        "login" in current_url or
                        "auth" in current_url or
                        "identity" in current_url or
                        "challenge" in current_url
                    )

                    # 2. Cek apakah input 2FA / kode OTP / Passkey masih ada di halaman
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

                    # 3. Cek keberadaan elemen dashboard Meta Business Suite yang sah (seperti Kotak Masuk / Pengelola Iklan)
                    has_dashboard_element = False
                    try:
                        if page.locator("text='Pengelola Iklan'").count() > 0 or \
                           page.locator("text='Kotak Masuk'").count() > 0 or \
                           page.locator("text='Konten'").count() > 0 or \
                           page.locator("text='Monetisasi'").count() > 0:
                            has_dashboard_element = True
                    except Exception:
                        pass

                    # HANYA anggap login selesai 100% jika:
                    # - Tidak dalam checkpoint / 2FA
                    # - Tidak ada input 2FA
                    # - Dan elemen dashboard sah sudah terlihat ATAU URL sudah murni di business.facebook.com/latest/home tanpa parameter login
                    if not is_in_checkpoint_or_2fa and not has_2fa_input and (has_dashboard_element or ("business.facebook.com/latest/home" in current_url and "login" not in current_url)):
                        # Tunggu 3 detik tambahan untuk memastikan cookie sesi tersimpan stabil setelah 2FA
                        time.sleep(3)
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
                    page.evaluate("alert('✅ LOGIN & VERIFIKASI 2FA BERHASIL 100%! Sesi otentikasi telah disimpan secara permanen.')")
                    time.sleep(3)
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
    print(json.dumps(res))
