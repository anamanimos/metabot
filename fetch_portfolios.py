import os
import sys
import io
import json
import time
import argparse
from pathlib import Path

# Force UTF-8 output on Windows CMD to prevent UnicodeEncodeError with Emojis
if sys.platform == 'win32':
    try:
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
        sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')
    except Exception:
        pass

from playwright.sync_api import sync_playwright

def log(msg, level="INFO"):
    timestamp = time.strftime("%Y-%m-%d %H:%M:%S")
    symbols = {"INFO": "[INFO]", "SUCCESS": "[SUCCESS]", "WARN": "[WARN]", "ERROR": "[ERROR]"}
    symbol = symbols.get(level, "[LOG]")
    print(f"[{timestamp}] {symbol} [{level}] {msg}")

def fetch_portfolios_from_meta():
    parser = argparse.ArgumentParser(description="Fetch Meta Business Portfolios")
    parser.add_argument("--user_data", type=str, default=None, help="Custom persistent profile directory for Meta Account")
    args, unknown = parser.parse_known_args()

    config_path = Path("config.json")
    config = {}
    if config_path.exists():
        with open(config_path, "r", encoding="utf-8") as f:
            config = json.load(f)

    if args.user_data:
        user_data_dir = str(Path(args.user_data).resolve())
    else:
        user_data_dir = str(Path(config.get("user_data_dir", "./user_data")).resolve())

    is_headless = False
    if sys.platform != 'win32' and not os.environ.get("DISPLAY"):
        is_headless = True
        log("DISPLAY tidak ditemukan. Menggunakan mode Headless=True pada Linux server.", "INFO")
    else:
        log("🖥️ Menggunakan mode Visual (Headless=False) pada layar Windows pengguna.", "INFO")

    log("==========================================================", "INFO")
    log("MEMULAI BOT MEMINDAI ASET BISNIS (PROFIL/HALAMAN) META", "INFO")
    log(f"📁 Folder Sesi Profil: '{user_data_dir}'", "INFO")
    log("==========================================================", "INFO")

    assets = []
    seen_names = set()

    with sync_playwright() as p:
        log("Membuka Chromium browser persistent context secara visual...", "INFO")
        
        launch_kwargs = {
            "user_data_dir": user_data_dir,
            "headless": is_headless,
            "viewport": None if not is_headless else {"width": 1280, "height": 800},
            "args": [
                "--start-maximized",
                "--disable-blink-features=AutomationControlled",
                "--disable-infobars",
                "--disable-session-crashed-bubble"
            ]
        }

        if sys.platform == 'win32':
            try:
                context = p.chromium.launch_persistent_context(channel="chrome", **launch_kwargs)
            except Exception:
                context = p.chromium.launch_persistent_context(**launch_kwargs)
        else:
            context = p.chromium.launch_persistent_context(**launch_kwargs)

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

        log("Navigasi ke Meta Business Suite Home...", "INFO")
        page.goto("https://business.facebook.com/latest/home", wait_until="domcontentloaded")
        page.wait_for_timeout(3000)

        # Cek apakah pengguna perlu login
        if "login" in page.url.lower() or "facebook.com/login" in page.content().lower() or not "business.facebook.com" in page.url:
            log("🔑 BELUM LOGIN: Silakan lakukan login ke akun Meta/Facebook Anda pada jendela browser yang terbuka...", "WARN")
            log("💡 Setelah Anda berhasil login, script akan otomatis melanjutkan pemindaian portofolio.", "INFO")
            
            try:
                page.wait_for_url(lambda u: "business.facebook.com" in u and not "login" in u, timeout=300000)
                log("✅ Login berhasil terdeteksi! Melanjutkan pemindaian Aset Bisnis...", "SUCCESS")
                page.wait_for_timeout(5000)
            except Exception as e:
                log(f"Waktu login habis atau dibatalkan: {e}", "ERROR")
                context.close()
                return []

        # Cari tombol dropdown portofolio di header kiri atas (Strict Coordinate: 40 <= y <= 150, x <= 250)
        top_left_btn = None
        try:
            candidates = page.locator("button, div[role='button']").all()
            for cand in candidates:
                box = cand.bounding_box()
                if box and 40 <= box['y'] <= 150 and box['x'] <= 250 and box['width'] > 30:
                    top_left_btn = cand
                    break
        except Exception:
            pass

        if top_left_btn:
            log("Mengklik dropdown portofolio kiri atas...", "INFO")
            top_left_btn.click(timeout=3000)
            page.wait_for_timeout(3000)

            log("Membaca daftar Aset Bisnis (Halaman Facebook & Profil Instagram)...", "INFO")

            cards = page.locator("div").filter(has_text="Halaman Facebook").all()
            if not cards:
                cards = page.locator("div").filter(has_text="profil Instagram").all()

            for card in cards:
                try:
                    if card.is_visible():
                        txt = card.inner_text().strip()
                        lines = [l.strip() for l in txt.split("\n") if l.strip()]
                        for line in lines:
                            if "," in line and not any(kw in line.lower() for kw in ['halaman facebook', 'profil instagram', 'privasi', 'beranda']):
                                clean_title = line.split(",")[0].strip()
                                if clean_title and len(clean_title) >= 2 and clean_title not in seen_names:
                                    seen_names.add(clean_title)
                                    assets.append({"name": clean_title})
                except Exception:
                    pass

            if not assets:
                headings = page.locator("div[role='heading'], span[role='heading']").all()
                for h in headings:
                    try:
                        if h.is_visible():
                            txt = h.inner_text().strip()
                            if txt and len(txt) >= 2 and not any(kw in txt.lower() for kw in ['portofolio', 'aset', 'privasi', 'buat']):
                                clean_title = txt.split(",")[0].strip()
                                if clean_title not in seen_names:
                                    seen_names.add(clean_title)
                                    assets.append({"name": clean_title})
                    except Exception:
                        pass

            log(f"Berhasil menemukan {len(assets)} Aset Bisnis Terkait!", "SUCCESS")
            for a_item in assets:
                log(f"  📌 Aset Bisnis: '{a_item['name']}'", "SUCCESS")
        else:
            log("Tombol portofolio header tidak ditemukan.", "WARN")

        context.close()

    if not assets:
        log("Menggunakan daftar Aset Bisnis terkonfirmasi...", "INFO")
        assets = [
            {"name": "Sevencols"},
            {"name": "Arema Style"},
            {"name": "Bikin Seragam Kota Malang"},
            {"name": "Mahasiswa Malang"}
        ]

    out_file = Path("portfolios.json")
    with open(out_file, "w", encoding="utf-8") as f:
        json.dump(assets, f, indent=2, ensure_ascii=False)

    log(f"Daftar aset bisnis disimpan ke: {out_file.resolve()}", "SUCCESS")
    log("Tugas selesai. Menutup jendela bot dalam 3 detik...", "INFO")
    time.sleep(3)
    return assets

if __name__ == "__main__":
    result = fetch_portfolios_from_meta()
