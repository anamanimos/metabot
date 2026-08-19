import os
import sys
import io
import json
import time
from pathlib import Path

# Force UTF-8 output on Windows CMD to prevent UnicodeEncodeError with Emojis
if sys.platform == 'win32':
    try:
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
        sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')
    except Exception:
        pass

from playwright.sync_api import sync_playwright

def main():
    print("==========================================================")
    print("🚀 ASISTEN LOGIN META BUSINESS SUITE & EXPORT STATE.JSON")
    print("==========================================================")
    print("1. Jendela browser Chromium akan terbuka otomatis.")
    print("2. Silakan lakukan Login ke Akun Meta / Facebook Anda.")
    print("3. Setelah berhasil masuk ke Dashboard Meta Business Suite,")
    print("   kembali ke terminal ini dan tekan tombol [ENTER].")
    print("==========================================================")

    user_data_dir = "./user_data"

    with sync_playwright() as p:
        context = p.chromium.launch_persistent_context(
            user_data_dir=user_data_dir,
            headless=False,
            viewport={"width": 1280, "height": 850},
            args=["--disable-blink-features=AutomationControlled"]
        )

        page = context.pages[0] if context.pages else context.new_page()
        print("\nMembuka Meta Business Suite: https://business.facebook.com/latest/home ...")
        page.goto("https://business.facebook.com/latest/home")

        input("\n👉 SETELAH ANDA SUDAH LOGIN DI BROWSER, TEKAN [ENTER] DI SINI UNTUK MENYIMPAN SESI...")

        state = context.storage_state()
        state_file = Path("state.json")
        with open(state_file, "w", encoding="utf-8") as f:
            json.dump(state, f, indent=2, ensure_ascii=False)

        print(f"\n✅ BERHASIL! Berkas otentikasi sesi '{state_file.resolve()}' telah disimpan!")
        print("👉 Langkah Selanjutnya:")
        print("   Buka Web UI https://meta.damaijaya.my.id/meta-accounts")
        print("   Klik '🔑 Import Sesi' -> Unggah file 'state.json' ini -> Klik Simpan!")
        print("==========================================================")

        context.close()

if __name__ == "__main__":
    main()
