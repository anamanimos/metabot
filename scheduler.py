import os
import sys
import io
import json
import time
import random
import sqlite3
import traceback
import requests
from datetime import datetime
from pathlib import Path

# Set shared Playwright browser path to /var/www/meta.damaijaya.my.id/ms-playwright if available
shared_browser_dir = Path("/var/www/meta.damaijaya.my.id/ms-playwright")
if shared_browser_dir.exists():
    os.environ["PLAYWRIGHT_BROWSERS_PATH"] = str(shared_browser_dir)

# Force UTF-8 output on Windows CMD to prevent UnicodeEncodeError with Emojis
if sys.platform == 'win32':
    try:
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
        sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')
    except Exception:
        pass

from playwright.sync_api import sync_playwright, TimeoutError as PlaywrightTimeoutError

# ============================================================================
# UTILITY & DATABASE SYNC FUNCTIONS (MYSQL & SQLITE)
# ============================================================================

def log(msg, level="INFO"):
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    symbols = {"INFO": "ℹ️", "SUCCESS": "✅", "WARN": "⚠️", "ERROR": "❌", "PROCESS": "🚀"}
    symbol = symbols.get(level, "🔹")
    print(f"[{timestamp}] {symbol} [{level}] {msg}")

def load_json(filepath):
    with open(filepath, "r", encoding="utf-8") as f:
        return json.load(f)

def get_db_config():
    """Membaca konfigurasi DB dari file .env"""
    db_config = {
        'host': '127.0.0.1',
        'port': 3306,
        'user': 'root',
        'password': '',
        'database': 'igbot'
    }
    env_path = Path('.env')
    if env_path.exists():
        with open(env_path, 'r', encoding='utf-8') as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith('#'):
                    continue
                if line.startswith('DB_HOST='):
                    db_config['host'] = line.split('=', 1)[1].strip('"\'')
                elif line.startswith('DB_PORT='):
                    try:
                        db_config['port'] = int(line.split('=', 1)[1].strip('"\''))
                    except ValueError:
                        pass
                elif line.startswith('DB_USERNAME='):
                    db_config['user'] = line.split('=', 1)[1].strip('"\'')
                elif line.startswith('DB_PASSWORD='):
                    db_config['password'] = line.split('=', 1)[1].strip('"\'')
                elif line.startswith('DB_DATABASE='):
                    db_config['database'] = line.split('=', 1)[1].strip('"\'')
    return db_config

def update_db_schedule_status(item_code, status, notes=None):
    """Memperbarui status antrean di database MySQL igbot secara real-time"""
    db_cfg = get_db_config()
    db_updated = False

    # 1. Coba MySQL dengan user dari .env
    try:
        import pymysql
        conn = pymysql.connect(
            host=db_cfg['host'],
            port=db_cfg['port'],
            user=db_cfg['user'],
            password=db_cfg['password'],
            database=db_cfg['database'],
            autocommit=True
        )
        cursor = conn.cursor()
        now_str = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        note_str = notes or f"Selesai diproses Playwright Bot pada {datetime.now().strftime('%Y-%m-%d %H:%M')}"
        cursor.execute(
            "UPDATE schedules SET status = %s, notes = %s, updated_at = %s WHERE item_code = %s",
            (status, note_str, now_str, item_code)
        )
        conn.close()
        db_updated = True
        log(f"Sync status MySQL ({item_code}) -> '{status}'", "INFO")
    except Exception as e:
        log(f"Percobaan MySQL 1 ({db_cfg['user']}) gagal: {e}", "WARN")

    # 2. Coba MySQL fallback user root jika percobaan 1 gagal
    if not db_updated and db_cfg['user'] != 'root':
        try:
            import pymysql
            conn = pymysql.connect(
                host='127.0.0.1',
                port=3306,
                user='root',
                password='',
                database=db_cfg['database'],
                autocommit=True
            )
            cursor = conn.cursor()
            now_str = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            note_str = notes or f"Selesai diproses Playwright Bot pada {datetime.now().strftime('%Y-%m-%d %H:%M')}"
            cursor.execute(
                "UPDATE schedules SET status = %s, notes = %s, updated_at = %s WHERE item_code = %s",
                (status, note_str, now_str, item_code)
            )
            conn.close()
            db_updated = True
            log(f"Sync status MySQL Fallback root ({item_code}) -> '{status}'", "INFO")
        except Exception as e:
            log(f"Percobaan MySQL Fallback root gagal: {e}", "WARN")

    # 3. Fallback ke SQLite jika ada
    if not db_updated:
        try:
            db_path = Path("database/database.sqlite")
            if db_path.exists():
                conn = sqlite3.connect(db_path)
                cursor = conn.cursor()
                cursor.execute(
                    "UPDATE schedules SET status = ?, notes = ?, updated_at = datetime('now') WHERE item_code = ?",
                    (status, notes or f"Selesai diproses Playwright Bot pada {datetime.now().strftime('%Y-%m-%d %H:%M')}", item_code)
                )
                conn.commit()
                conn.close()
                log(f"Sync status SQLite ({item_code}) -> '{status}'", "INFO")
        except Exception as e:
            log(f"Catatan sync DB status SQLite ({item_code}): {e}", "WARN")

def format_date_no_padding(iso_date_str):
    """Konversi '2026-08-20' menjadi '20/8/2026'"""
    year, month, day = iso_date_str.split("-")
    return f"{int(day)}/{int(month)}/{year}"

def download_media_if_url(media_path, custom_filename=None, temp_dir="./temp_media"):
    """Mengunduh file dari URL HTTP(S) atau mengembalikan path lokal"""
    if media_path.startswith("http://") or media_path.startswith("https://"):
        os.makedirs(temp_dir, exist_ok=True)
        filename = custom_filename or media_path.split("/")[-1].split("?")[0]
        if not filename or len(filename) < 3:
            filename = f"media_{int(time.time())}_{random.randint(10,99)}.jpg"
        
        target_file = os.path.join(temp_dir, filename)
        log(f"Mengunduh file media eksternal dari: {media_path}...", "INFO")
        
        response = requests.get(media_path, timeout=30)
        response.raise_for_status()
        with open(target_file, "wb") as f:
            f.write(response.content)
            
        return str(Path(target_file).resolve())
    else:
        local_path = Path(media_path).resolve()
        if not local_path.exists():
            alt_path = Path("public" + media_path if media_path.startswith("/") else "public/" + media_path).resolve()
            if alt_path.exists():
                return str(alt_path)
            raise FileNotFoundError(f"File media lokal tidak ditemukan pada sistem: '{media_path}'")
        return str(local_path)

def select_portfolio(page, target_portfolio_name):
    """Memilih Portofolio Meta Business Suite dari dropdown"""
    log(f"Mencari dan memilih Portofolio Target: '{target_portfolio_name}'...", "PROCESS")
    
    dropdown_triggers = [
        "div[role='button']:has-text('Aroma Style')",
        "button:has-text('Aroma Style')",
        "div[aria-haspopup='listbox']",
        "div[data-testid='business-selector-trigger']",
        "header div[role='button']",
    ]
    
    trigger_found = False
    for selector in dropdown_triggers:
        try:
            el = page.locator(selector).first
            if el.is_visible(timeout=2000):
                el.click()
                log(f"Menu dropdown Portofolio diklik via selector: {selector}", "SUCCESS")
                trigger_found = True
                break
        except Exception:
            continue
            
    if not trigger_found:
        try:
            page.locator("header").locator("div[role='button']").first.click(timeout=3000)
            log("Klik fallback dropdown header portofolio.", "INFO")
        except Exception as e:
            log(f"Dropdown portofolio mungkin sudah terbuka atau tidak ditemukan: {e}", "WARN")

    time.sleep(1.5)

    item_selectors = [
        f"text=\"{target_portfolio_name}\"",
        f"div[role='option']:has-text('{target_portfolio_name}')",
        f"li:has-text('{target_portfolio_name}')",
        f"span:has-text('{target_portfolio_name}')"
    ]
    
    selected = False
    for sel in item_selectors:
        try:
            item = page.locator(sel).first
            if item.is_visible(timeout=3000):
                item.click()
                log(f"Portofolio '{target_portfolio_name}' berhasil dipilih!", "SUCCESS")
                selected = True
                time.sleep(3)
                break
        except Exception:
            continue
            
    if not selected:
        log(f"Portofolio '{target_portfolio_name}' tidak dapat diklik. Mengasumsikan portofolio aktif adalah '{target_portfolio_name}'.", "WARN")

def schedule_story_item(page, item):
    item_id = item.get("id") or item.get("item_code") or "N/A"
    portfolio_name = item.get("portfolioName") or item.get("portfolio_name")
    raw_date = item.get("date") or item.get("target_date")
    time_str = item.get("time") or item.get("target_time")
    
    media_paths = item.get("mediaPaths") or item.get("media_paths")
    if not media_paths and item.get("mediaPath"):
        media_paths = [item.get("mediaPath")]
    elif not media_paths and item.get("media_path"):
        media_paths = [item.get("media_path")]

    log(f"=== MEMPROSES SCHEDULE ITEM [{item_id}] ===", "PROCESS")
    log(f"Target Portofolio: {portfolio_name} | Tanggal: {raw_date} | Jam: {time_str}", "INFO")

    if not media_paths:
        raise ValueError(f"Schedule item [{item_id}] tidak memiliki path media!")

    prepared_media_files = []
    for idx, m_path in enumerate(media_paths):
        local_file = download_media_if_url(m_path, custom_filename=f"{item_id}_{idx+1}.jpg")
        prepared_media_files.append(local_file)

    composer_url = "https://business.facebook.com/latest/composer?asset_id=&context_ref=REDESIGN_HOME_PAGE_SCHEDULE_POST_BUTTON&is_story=true&nav_ref=bm_home_schedule_post_button"
    log(f"Membuka Meta Story Composer: {composer_url}", "INFO")
    page.goto(composer_url, wait_until="networkidle")
    time.sleep(3)

    if portfolio_name:
        try:
            select_portfolio(page, portfolio_name)
        except Exception as e:
            log(f"Gagal memilih portofolio secara otomatis: {e}", "WARN")

    log(f"Mengunggah {len(prepared_media_files)} file media ke Story Composer...", "PROCESS")
    
    upload_selectors = [
        "input[type='file'][accept*='image']",
        "input[type='file'][accept*='video']",
        "input[type='file']"
    ]
    
    file_input_found = False
    for sel in upload_selectors:
        try:
            file_input = page.locator(sel).first
            if file_input.count() > 0:
                file_input.set_input_files(prepared_media_files)
                log(f"File media berhasil disuntikkan ke selector '{sel}'", "SUCCESS")
                file_input_found = True
                break
        except Exception:
            continue
            
    if not file_input_found:
        raise RuntimeError("Input file upload media tidak ditemukan pada Meta Story Composer!")

    log("Menunggu proses preview media selesai diunggah di browser...", "INFO")
    time.sleep(8)

    log("Mengaktifkan opsi 'Schedule' (Jadwalkan)...", "PROCESS")
    
    schedule_radio_selectors = [
        "input[type='radio'][value='SCHEDULE']",
        "div[role='radio']:has-text('Schedule')",
        "div[role='radio']:has-text('Jadwalkan')",
        "text='Schedule'",
        "text='Jadwalkan'"
    ]
    
    radio_clicked = False
    for sel in schedule_radio_selectors:
        try:
            r = page.locator(sel).first
            if r.is_visible(timeout=2000):
                r.click()
                log(f"Opsi Penjadwalan diklik via selector: {sel}", "SUCCESS")
                radio_clicked = True
                break
        except Exception:
            continue

    if not radio_clicked:
        log("Mencoba klik alternatif label Schedule/Jadwalkan...", "INFO")
        page.locator("label:has-text('Schedule'), label:has-text('Jadwalkan')").first.click(timeout=3000)

    time.sleep(2)

    if "/" in raw_date:
        formatted_date = raw_date
    else:
        formatted_date = format_date_no_padding(raw_date)

    log(f"Mengisikan Tanggal Target: '{formatted_date}' & Waktu Target: '{time_str}'...", "PROCESS")

    date_inputs = page.locator("input[placeholder*='mm/dd'], input[placeholder*='dd/mm'], input[placeholder*='d/m'], input[value*='/']").all()
    if date_inputs:
        for idx, d_in in enumerate(date_inputs):
            try:
                if d_in.is_visible():
                    d_in.click()
                    page.keyboard.press("Control+A")
                    page.keyboard.press("Backspace")
                    d_in.fill(formatted_date)
                    d_in.dispatch_event("change")
                    log(f"Input Tanggal [{idx+1}] diisi -> '{formatted_date}'", "SUCCESS")
            except Exception as e:
                log(f"Gagal mengisi date input [{idx+1}]: {e}", "WARN")

    time.sleep(1)

    time_inputs = page.locator("input[placeholder*='hh:mm'], input[placeholder*='Jam'], input[value*=':']").all()
    if time_inputs:
        for idx, t_in in enumerate(time_inputs):
            try:
                if t_in.is_visible():
                    t_in.click()
                    page.keyboard.press("Control+A")
                    page.keyboard.press("Backspace")
                    t_in.fill(time_str)
                    t_in.dispatch_event("change")
                    log(f"Input Waktu [{idx+1}] diisi -> '{time_str}'", "SUCCESS")
            except Exception as e:
                log(f"Gagal mengisi time input [{idx+1}]: {e}", "WARN")

    time.sleep(3)

    log("Mencari dan menekan tombol 'Schedule' / 'Jadwalkan' final...", "PROCESS")
    
    schedule_btn_selectors = [
        "button:has-text('Schedule')",
        "button:has-text('Jadwalkan')",
        "div[role='button']:has-text('Schedule')",
        "div[role='button']:has-text('Jadwalkan')",
        "button[type='submit']"
    ]
    
    btn_clicked = False
    for sel in schedule_btn_selectors:
        try:
            btns = page.locator(sel).all()
            for b in btns:
                if b.is_visible() and b.is_enabled():
                    b.click()
                    log(f"Tombol Schedule diklik via selector: {sel}", "SUCCESS")
                    btn_clicked = True
                    break
            if btn_clicked:
                break
        except Exception:
            continue

    if not btn_clicked:
        raise RuntimeError("Tombol final Schedule/Jadwalkan tidak dapat diklik!")

    log("Menunggu Meta memproses konfirmasi penjadwalan...", "INFO")
    time.sleep(10)
    log(f"=== BERHASIL MENJADWALKAN ITEM [{item_id}] SAAT INI JUGA! ===", "SUCCESS")

    update_db_schedule_status(item_id, "completed", f"Sukses dipublish/dijadwalkan ke Meta pada {datetime.now().strftime('%Y-%m-%d %H:%M')}")

# ============================================================================
# MAIN SCRIPT EXECUTION
# ============================================================================

def main():
    log("🚀 Memulai Executing Meta Story Auto Scheduler Bot...", "INFO")

    schedule_file = Path("schedule.json")
    if not schedule_file.exists():
        schedule_file = Path("config.json")

    if not schedule_file.exists():
        log("File schedule.json / config.json tidak ditemukan!", "ERROR")
        sys.exit(1)

    schedules = load_json(schedule_file)
    if not schedules:
        log("File schedule.json kosong! Tidak ada antrean PENDING.", "WARN")
        sys.exit(0)

    log(f"Menemukan {len(schedules)} item PENDING dalam antrean schedule.json.", "INFO")

    user_data_dir = None
    for arg in sys.argv:
        if arg.startswith("--user_data="):
            user_data_dir = arg.split("=", 1)[1]

    if not user_data_dir:
        for entry in os.listdir("."):
            if entry.startswith("user_data") and os.path.isdir(entry):
                user_data_dir = os.path.join(".", entry)
                break

    if not user_data_dir:
        user_data_dir = "./user_data"

    log(f"Menggunakan profil sesi browser: {user_data_dir}", "INFO")

    # Deteksi headless secara otomatis: jika tidak ada DISPLAY (di Linux server), gunakan headless=True
    is_headless = False
    if sys.platform != 'win32' and not os.environ.get("DISPLAY"):
        is_headless = True
        log("DISPLAY tidak ditemukan. Menggunakan mode Headless=True pada Linux server.", "INFO")

    with sync_playwright() as p:
        context = p.chromium.launch_persistent_context(
            user_data_dir=user_data_dir,
            headless=is_headless,
            viewport={"width": 1280, "height": 900},
            args=[
                "--disable-blink-features=AutomationControlled",
                "--no-sandbox",
                "--disable-dev-shm-usage"
            ]
        )
        
        page = context.pages[0] if context.pages else context.new_page()

        success_count = 0
        fail_count = 0

        for item in schedules:
            item_code = item.get("id") or item.get("item_code") or "N/A"
            try:
                schedule_story_item(page, item)
                success_count += 1
            except Exception as e:
                fail_count += 1
                error_detail = str(e).strip().replace('\n', ' ')
                log(f"GAGAL menjadwalkan item [{item_code}]: {error_detail}", "ERROR")
                update_db_schedule_status(item_code, "failed", f"❌ ERROR BOT ({datetime.now().strftime('%H:%M')}): {error_detail[:250]}")

        context.close()

    log(f"==================================================", "INFO")
    log(f"🏁 BOT EKSEKUSI SELESAI! Sukses: {success_count} | Gagal: {fail_count}", "SUCCESS")
    log(f"==================================================", "INFO")

if __name__ == "__main__":
    main()
