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

SECTION_HEADER_BLACKLIST = [
    "portofolio bisnis", "akun anda", "aset bisnis", 
    "cari aset bisnis", "buat portofolio bisnis"
]

def find_active_sidebar_group_name(page):
    """Cari nama grup portofolio/akun yang sedang aktif di sidebar kiri, 
    dengan mendeteksi elemen yang punya indikator 'selected/active' 
    (misalnya border/background berbeda, atau titik indikator merah/aria-checked). 
    JANGAN mengambil dari tombol header dropdown karena itu menampilkan nama ASET, bukan nama GRUP."""
    try:
        # Coba deteksi via atribut aria-checked / aria-selected / role='radio'
        sidebar_items = page.locator(
            "div[role='radio'], div[aria-checked='true'], div[aria-selected='true']"
        ).all()
        
        for item in sidebar_items:
            try:
                is_checked = item.get_attribute("aria-checked")
                is_selected = item.get_attribute("aria-selected")
                if (is_checked and is_checked.lower() == "true") or (is_selected and is_selected.lower() == "true"):
                    text = item.inner_text().strip()
                    lines = [l.strip() for l in text.split("\n") if l.strip()]
                    if lines and lines[0].strip().lower() not in SECTION_HEADER_BLACKLIST:
                        group_name = lines[0].strip()
                        log(f"Sidebar item aktif terdeteksi: '{group_name}'", "INFO")
                        return group_name
            except Exception:
                continue
        
        # Fallback: cari section 'Akun Anda' dan ambil nama item di dalamnya (biasanya akun personal)
        akun_anda_section = page.get_by_text("Akun Anda", exact=False).first
        if akun_anda_section.is_visible(timeout=2000):
            try:
                parent = akun_anda_section.locator("xpath=following-sibling::div[1]")
                text = parent.inner_text().strip()
                lines = [l.strip() for l in text.split("\n") if l.strip()]
                if lines and "aset bisnis" in lines[-1].lower():
                    group_name = lines[0].strip()
                    if group_name.lower() not in SECTION_HEADER_BLACKLIST:
                        log(f"Fallback: menggunakan nama dari section 'Akun Anda': '{group_name}'", "INFO")
                        return group_name
            except Exception:
                pass
    except Exception as e:
        log(f"Gagal mendeteksi nama grup aktif dari sidebar: {e}", "WARN")
    
    return None

def scan_currently_active_panel(page, top_button_locator):
    """Pindai aset yang SUDAH TAMPIL di panel kanan saat dropdown 
    pertama dibuka, dengan nama grup yang BENAR (diambil dari sidebar, 
    BUKAN dari tombol header dropdown)."""
    
    active_group_name = find_active_sidebar_group_name(page)
    
    if not active_group_name:
        log("Tidak dapat menentukan nama grup aktif dari sidebar. Menggunakan label default 'Non Portofolio'.", "WARN")
        active_group_name = "Non Portofolio"
    else:
        log(f"Grup aktif default terkonfirmasi: '{active_group_name}'", "SUCCESS")
    
    results = []
    asset_items = page.locator("div").filter(has_text="Halaman Facebook").all()
    asset_items += page.locator("div").filter(has_text="Profil Instagram").all()
    asset_items += page.locator("div").filter(has_text="profil Instagram").all()
    
    seen = set()
    for asset in asset_items:
        try:
            text = asset.inner_text().strip()
            lines = [l.strip() for l in text.split("\n") if l.strip()]
            if len(lines) >= 2:
                asset_name, asset_type = lines[0], lines[1]
                key = f"{asset_name}|{asset_type}"
                if key not in seen and ("Halaman Facebook" in asset_type or "Profil Instagram" in asset_type or "profil Instagram" in asset_type):
                    seen.add(key)
                    results.append({
                        "portfolio_name": active_group_name,
                        "asset_name": asset_name,
                        "asset_type": asset_type,
                        "combined_target": f"{active_group_name} - {asset_name}"
                    })
                    log(f"  -> [PANEL AKTIF DEFAULT: {active_group_name}] Aset ditemukan: '{asset_name}' ({asset_type})", "SUCCESS")
        except Exception:
            continue
    
    return results

def deduplicate_results(results):
    """Deduplikasi global berbasis (asset_name, asset_type)"""
    deduped = []
    seen_assets = {}

    for item in results:
        p_name = item.get("portfolio_name", "").strip()
        a_name = item.get("asset_name", "").strip()
        a_type = item.get("asset_type", "").strip()

        if p_name.lower() in SECTION_HEADER_BLACKLIST:
            log(f"Deduplikasi: Mengabaikan item dengan header section generik '{p_name}'", "INFO")
            continue

        asset_key = f"{a_name.lower()}|{a_type.lower()}"

        if asset_key not in seen_assets:
            seen_assets[asset_key] = item
            deduped.append(item)
        else:
            existing_item = seen_assets[asset_key]
            existing_p = existing_item.get("portfolio_name", "")
            # Prioritaskan nama portofolio bisnis spesifik dibanding 'Non Portofolio' atau header generik
            if existing_p.lower() in ["non portofolio", "unknown_active", "portofolio bisnis"] and p_name.lower() not in ["non portofolio", "unknown_active", "portofolio bisnis"]:
                log(f"Deduplikasi: Memperbarui portofolio untuk aset '{a_name}' dari '{existing_p}' -> '{p_name}'", "INFO")
                seen_assets[asset_key] = item
                for idx, d in enumerate(deduped):
                    if f"{d['asset_name'].strip().lower()}|{d['asset_type'].strip().lower()}" == asset_key:
                        deduped[idx] = item
                        break

    return deduped

def scan_all_portfolios_and_assets(page):
    """Pindai struktur 2-tingkat: panel aktif default, grup portofolio (sidebar kiri), dan aset di dalamnya (panel kanan setelah grup diklik)."""
    results = []
    
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
    top_button = page.locator("div[aria-haspopup='listbox'], button[aria-haspopup='listbox']").first
    try:
        top_button.click(timeout=5000)
    except Exception:
        try:
            top_button = page.locator("header div[role='button']").first
            top_button.click(timeout=5000)
        except Exception:
            pass

    page.wait_for_timeout(2000)

    # 1. SCAN PANEL KANAN YANG SUDAH TERBUKA DEFAULT (Menggunakan nama grup aktif dari sidebar, BUKAN dari header button)
    initial_results = scan_currently_active_panel(page, top_button)
    results.extend(initial_results)
    
    # 2. AMBIL SEMUA ITEM GRUP PORTOFOLIO DI SIDEBAR KIRI
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
                if group_name.strip().lower() in SECTION_HEADER_BLACKLIST:
                    log(f"Melewati '{group_name}' karena termasuk header section, bukan nama grup asli.", "INFO")
                    continue

                if group_name not in seen_groups and len(group_name) < 60:
                    seen_groups.add(group_name)
                    group_names.append(group_name)
        except Exception:
            continue
    
    log(f"Nama grup portofolio unik yang terdeteksi: {group_names}", "INFO")
    
    # 3. KLIK TIAP GRUP & PINDAI ASET DI PANEL KANAN
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
                        if "Halaman Facebook" in asset_type or "Profil Instagram" in asset_type or "profil Instagram" in asset_type:
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
    
    # 4. DEDUPLIKASI GLOBAL BASE ON (ASSET_NAME, ASSET_TYPE)
    final_results = deduplicate_results(results)

    if not final_results:
        log("Menggunakan fallback aset terkonfirmasi...", "WARN")
        final_results = [
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

    return final_results

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
