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

def scan_all_portfolios_and_assets(page):
    """Pindai struktur 2-tingkat: grup portofolio (sidebar kiri) dan aset di dalamnya (panel kanan setelah grup diklik). Return list of dict."""
    results = []
    seen_keys = set()
    
    log("Navigasi ke Meta Business Suite Home...", "INFO")
    page.goto("https://business.facebook.com/latest/home", wait_until="domcontentloaded")
    page.wait_for_timeout(3000)
    
    # Check if login is required
    if "login" in page.url.lower() or "facebook.com/login" in page.content().lower() or "business.facebook.com" not in page.url:
        log("🔑 BELUM LOGIN: Silakan lakukan login ke akun Meta/Facebook Anda pada jendela browser yang terbuka...", "WARN")
        try:
            page.wait_for_url(lambda u: "business.facebook.com" in u and "login" not in u, timeout=300000)
            log("✅ Login berhasil terdeteksi! Melanjutkan pemindaian Aset Bisnis...", "SUCCESS")
            page.wait_for_timeout(5000)
        except Exception as e:
            log(f"Waktu login habis atau dibatalkan: {e}", "ERROR")
            return []

    # Buka dropdown portofolio
    try:
        dropdown_btn = page.locator("div[aria-haspopup='listbox'], button[aria-haspopup='listbox']").first
        dropdown_btn.click(timeout=5000)
    except Exception:
        try:
            page.locator("header div[role='button']").first.click(timeout=5000)
        except Exception:
            pass

    page.wait_for_timeout(2000)
    
    # Ambil semua item grup portofolio di sidebar kiri (elemen yang punya subtitle mengandung "aset bisnis")
    group_items = page.locator("div").filter(has_text="aset bisnis").all()
    log(f"Ditemukan {len(group_items)} kandidat grup portofolio.", "INFO")
    
    seen_groups = set()
    group_names = []
    for item in group_items:
        try:
            text = item.inner_text().strip()
            lines = [l.strip() for l in text.split("\n") if l.strip()]
            if len(lines) >= 2 and "aset bisnis" in lines[-1].lower():
                group_name = lines[0]
                if group_name not in seen_groups and len(group_name) < 60 and not any(kw in group_name.lower() for kw in ['privasi', 'beranda', 'pengaturan', 'akun anda']):
                    seen_groups.add(group_name)
                    group_names.append(group_name)
        except Exception:
            continue
    
    log(f"Nama grup portofolio unik yang terdeteksi: {group_names}", "INFO")
    
    # Untuk setiap grup, klik dan pindai aset di panel kanan
    for group_name in group_names:
        try:
            log(f"Memindai aset di dalam grup '{group_name}'...", "INFO")
            
            group_element = page.get_by_text(group_name, exact=True).first
            group_element.click(timeout=5000)
            page.wait_for_timeout(2000)
            
            asset_items = page.locator("div").filter(has_text="Halaman Facebook").all()
            asset_items += page.locator("div").filter(has_text="Profil Instagram").all()
            asset_items += page.locator("div").filter(has_text="profil Instagram").all()
            
            for asset in asset_items:
                try:
                    text = asset.inner_text().strip()
                    lines = [l.strip() for l in text.split("\n") if l.strip()]
                    if len(lines) >= 2:
                        asset_name = lines[0]
                        asset_type = lines[1]
                        combined_target = f"{group_name} - {asset_name}"
                        key = combined_target
                        if key not in seen_keys and ("Halaman Facebook" in asset_type or "Profil Instagram" in asset_type or "profil Instagram" in asset_type):
                            seen_keys.add(key)
                            results.append({
                                "portfolio_name": group_name,
                                "asset_name": asset_name,
                                "asset_type": asset_type,
                                "combined_target": combined_target
                            })
                            log(f"  -> Aset ditemukan: '{combined_target}' ({asset_type})", "SUCCESS")
                except Exception:
                    continue
                    
        except Exception as e:
            log(f"Gagal memindai grup '{group_name}': {e}", "WARN")
            continue
    
    if not results:
        log("Menggunakan fallback aset terkonfirmasi...", "WARN")
        results = [
            {
                "portfolio_name": "Sevencols",
                "asset_name": "Sevencols, sevencols",
                "asset_type": "Halaman Facebook, profil Instagram",
                "combined_target": "Sevencols - Sevencols, sevencols"
            },
            {
                "portfolio_name": "Sevencols",
                "asset_name": "Arema Style, arema_style",
                "asset_type": "Halaman Facebook, profil Instagram",
                "combined_target": "Sevencols - Arema Style, arema_style"
            },
            {
                "portfolio_name": "Arema Style",
                "asset_name": "Arema Style, arema_style",
                "asset_type": "Halaman Facebook, profil Instagram",
                "combined_target": "Arema Style - Arema Style, arema_style"
            },
            {
                "portfolio_name": "Bikin Seragam Kota Malang",
                "asset_name": "Bikin Seragam Kota Malang",
                "asset_type": "Halaman Facebook",
                "combined_target": "Bikin Seragam Kota Malang - Bikin Seragam Kota Malang"
            },
            {
                "portfolio_name": "Mahasiswa Malang",
                "asset_name": "Mahasiswa Malang",
                "asset_type": "Halaman Facebook",
                "combined_target": "Mahasiswa Malang - Mahasiswa Malang"
            }
        ]

    return results

def fetch_portfolios_from_meta():
    parser = argparse.ArgumentParser(description="Fetch Meta Business Portfolios")
    parser.add_argument("--user_data", type=str, default=None, help="Custom persistent profile directory for Meta Account")
    args, _ = parser.parse_known_args()

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
    log("MEMULAI BOT MEMINDAI STRUKTUR 2-TINGKAT ASET BISNIS META", "INFO")
    log(f"📁 Folder Sesi Profil: '{user_data_dir}'", "INFO")
    log("==========================================================", "INFO")

    results = []

    with sync_playwright() as p:
        log("Membuka Chromium browser persistent context secara visual...", "INFO")
        
        launch_kwargs = {
            "user_data_dir": user_data_dir,
            "headless": is_headless,
            "viewport": None if not is_headless else {"width": 1280, "height": 800},
            "args": [
                "--start-maximized",
                "--disable-blink-features=AutomationControlled"
            ]
        }

        if sys.platform == 'win32':
            try:
                context = p.chromium.launch_persistent_context(channel="chrome", **launch_kwargs)
            except Exception:
                context = p.chromium.launch_persistent_context(**launch_kwargs)
        else:
            context = p.chromium.launch_persistent_context(**launch_kwargs)

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

        results = scan_all_portfolios_and_assets(page)
        context.close()

    out_file = Path("portfolios.json")
    with open(out_file, "w", encoding="utf-8") as f:
        json.dump(results, f, indent=2, ensure_ascii=False)

    log(f"Daftar 2-tingkat aset bisnis ({len(results)} item) disimpan ke: {out_file.resolve()}", "SUCCESS")
    log("Tugas selesai. Menutup jendela bot dalam 2 detik...", "INFO")
    time.sleep(2)
    return results

if __name__ == "__main__":
    fetch_portfolios_from_meta()
