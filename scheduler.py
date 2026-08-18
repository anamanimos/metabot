import os
import sys
import io
import json
import time
import random
import sqlite3
import requests
from datetime import datetime
from pathlib import Path

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

def update_db_schedule_status(item_code, status, notes=None):
    """Memperbarui status antrean di database MySQL igbot secara real-time"""
    try:
        import pymysql
        conn = pymysql.connect(
            host='127.0.0.1',
            port=3306,
            user='root',
            password='',
            database='igbot',
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
        log(f"Sync status MySQL ({item_code}) -> '{status}'", "INFO")
    except Exception as e:
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
        except Exception:
            pass
        log(f"Catatan sync DB status ({item_code}): {e}", "WARN")

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
            raise FileNotFoundError(f"File media lokal tidak ditemukan: {media_path}")
        return str(local_path)

def fill_input_field(input_element, text_value, field_name="Input"):
    """Helper mengisi input teks/tanggal dengan simulasi ketik & trigger event"""
    try:
        page_obj = input_element.page
        input_element.scroll_into_view_if_needed(timeout=2000)
        input_element.click(force=True)
        page_obj.wait_for_timeout(300)
        
        input_element.press("Control+A")
        input_element.press("Backspace")
        page_obj.wait_for_timeout(300)
        
        input_element.type(text_value, delay=50)
        page_obj.wait_for_timeout(300)
        
        input_element.press("Enter")
        input_element.press("Tab")
        page_obj.wait_for_timeout(200)

        actual_val = input_element.input_value()
        if actual_val == text_value:
            log(f"Field '{field_name}' berhasil terisi: '{actual_val}'", "SUCCESS")
        else:
            log(f"Field '{field_name}' terisi '{actual_val}' (Target: '{text_value}')", "WARN")
            
    except Exception as e:
        log(f"Gagal mengisi field '{field_name}': {e}", "WARN")

# ============================================================================
# SPINBUTTON TIME FILLING HELPERS (PERBAIKAN WAKTU META BUSINESS SUITE)
# ============================================================================

def fill_time_spinbutton(page, spinbutton_locator, target_value, label=""):
    """Isi elemen input[role='spinbutton'] (jam atau menit) dengan verifikasi aria-valuenow"""
    try:
        spinbutton_locator.click(timeout=3000)
        page.wait_for_timeout(200)
        spinbutton_locator.press("Control+A")
        spinbutton_locator.press("Backspace")
        page.wait_for_timeout(200)
        spinbutton_locator.type(str(int(target_value)), delay=80)
        page.wait_for_timeout(200)
        spinbutton_locator.press("Tab")
        page.wait_for_timeout(300)

        actual = spinbutton_locator.get_attribute("aria-valuenow")
        if actual is None or int(actual) != int(target_value):
            log(f"Verifikasi {label}: target={target_value}, aktual aria-valuenow={actual}. Tidak sesuai, retry...", "WARN")
            spinbutton_locator.click(timeout=3000)
            spinbutton_locator.press("Control+A")
            spinbutton_locator.press("Backspace")
            page.wait_for_timeout(200)
            spinbutton_locator.type(str(int(target_value)), delay=80)
            spinbutton_locator.press("Tab")
            page.wait_for_timeout(300)
            actual_retry = spinbutton_locator.get_attribute("aria-valuenow")
            if actual_retry is None or int(actual_retry) != int(target_value):
                log(f"GAGAL mengisi {label} setelah retry. Aktual: {actual_retry}", "ERROR")
                return False
            else:
                log(f"{label} berhasil diisi setelah retry: {actual_retry}", "SUCCESS")
                return True
        else:
            log(f"{label} terverifikasi sesuai target: {actual}", "SUCCESS")
            return True
    except Exception as e:
        log(f"Error mengisi {label}: {e}", "ERROR")
        return False

def fill_time_field(page, target_time_str, section_index=0):
    """Isi waktu HH:mm pada section_index (0 = Facebook, 1 = Instagram)"""
    target_hour, target_minute = target_time_str.split(":")
    
    hour_inputs = page.locator("input[role='spinbutton'][aria-label*='jam'], input[role='spinbutton'][aria-label*='hour']").all()
    minute_inputs = page.locator("input[role='spinbutton'][aria-label*='menit'], input[role='spinbutton'][aria-label*='minute']").all()

    if not hour_inputs or not minute_inputs:
        all_spinbuttons = page.locator("input[role='spinbutton']").all()
        if len(all_spinbuttons) >= 2:
            hour_inputs = [all_spinbuttons[idx] for idx in range(0, len(all_spinbuttons), 2) if all_spinbuttons[idx].is_visible()]
            minute_inputs = [all_spinbuttons[idx] for idx in range(1, len(all_spinbuttons), 2) if all_spinbuttons[idx].is_visible()]

    if len(hour_inputs) <= section_index or len(minute_inputs) <= section_index:
        log(f"Input waktu untuk section index {section_index} tidak ditemukan.", "WARN")
        return False
    
    success_hour = fill_time_spinbutton(page, hour_inputs[section_index], target_hour, f"Jam (section {section_index})")
    success_minute = fill_time_spinbutton(page, minute_inputs[section_index], target_minute, f"Menit (section {section_index})")
    
    return success_hour and success_minute

def switch_to_jadwalkan_mode(page, max_retries=3, item_id="default"):
    """Klik toggle 'Jadwalkan' dan verifikasi mode berpindah"""
    log("Tahap 4: Memilih opsi 'Jadwalkan'...", "INFO")
    
    for attempt in range(1, max_retries + 1):
        try:
            toggle_container = page.locator("div").filter(
                has_text="Bagikan sekarang"
            ).filter(has_text="Jadwalkan").last
            
            jadwalkan_btn = toggle_container.get_by_text("Jadwalkan", exact=True).first
            jadwalkan_btn.click(timeout=5000)
            page.wait_for_timeout(1500)
            
            page.wait_for_selector(
                "input[placeholder='dd/mm/yyyy']", 
                state="visible", 
                timeout=5000
            )
            log("Mode 'Jadwalkan' terverifikasi aktif.", "SUCCESS")
            return True
            
        except (PlaywrightTimeoutError, Exception) as e:
            log(f"Percobaan #{attempt}: Field tanggal belum muncul ({e}), retry...", "WARN")
            if attempt < max_retries:
                page.wait_for_timeout(1500)

    raise Exception(f"Mode 'Jadwalkan' gagal diaktifkan setelah {max_retries} kali percobaan.")

def select_portfolio_on_home(page, portfolio_name):
    """Memilih Aset/Portofolio Bisnis di Halaman Beranda"""
    if not portfolio_name:
        return
        
    log(f"Memeriksa Portofolio Bisnis target: '{portfolio_name}'...", "INFO")
    page.wait_for_timeout(2000)

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
        try:
            btn_text = top_left_btn.inner_text().strip()
            if portfolio_name.lower() in btn_text.lower():
                log(f"Portofolio '{portfolio_name}' sudah terdeteksi AKTIF di header.", "SUCCESS")
                return
        except Exception:
            pass

    if top_left_btn:
        try:
            top_left_btn.click(timeout=3000)
            page.wait_for_timeout(2000)

            portfolio_item = page.get_by_text(portfolio_name, exact=True).first
            if not portfolio_item.is_visible():
                portfolio_item = page.get_by_text(portfolio_name, exact=False).first

            portfolio_item.click(timeout=4000)
            page.wait_for_timeout(3000)
            log(f"Portofolio '{portfolio_name}' berhasil dipilih!", "SUCCESS")
        except Exception as e:
            log(f"Catatan pemilihan portofolio: {e}", "WARN")

def upload_file_safely(page, media_file_paths):
    """
    Mengunggah file media (Single atau Multi-Image) sekaligus ke Meta Story Composer.
    """
    log("Tahap 3: Mengunggah file media (Multi-Image Support) ke Story Composer...", "INFO")
    
    raw_paths = media_file_paths if isinstance(media_file_paths, list) else [media_file_paths]
    downloaded_paths = []

    for idx, p in enumerate(raw_paths):
        downloaded_paths.append(download_media_if_url(p, f"media_{idx+1}.jpg"))

    valid_paths = [str(Path(p).resolve()) for p in downloaded_paths if p]
    log(f"Jumlah file media yang akan disuntikkan: {len(valid_paths)}", "INFO")
    for vp in valid_paths:
        log(f"  📷 Media Path: {vp}", "INFO")

    try:
        file_input = page.wait_for_selector("input[type='file']", state="attached", timeout=15000)
        if file_input:
            file_input.set_input_files(valid_paths)
            page.evaluate("""() => {
                const inputs = document.querySelectorAll('input[type="file"]');
                inputs.forEach(input => {
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }""")
            log(f"Berhasil menyuntikkan {len(valid_paths)} file media sekaligus ke input[type='file']!", "SUCCESS")
            return
    except Exception as e:
        log(f"Metode utama injeksi file gagal: {e}. Mencoba fallback FileChooser...", "WARN")

    try:
        upload_btn = page.locator("button, label, div[role='button']").filter(
            has_text="Tambahkan foto/video"
        ).first

        if upload_btn.is_visible(timeout=5000):
            with page.expect_file_chooser(timeout=10000) as fc_info:
                upload_btn.click()
            file_chooser = fc_info.value
            file_chooser.set_files(valid_paths)
            log(f"Berhasil mengunggah {len(valid_paths)} file media via FileChooser!", "SUCCESS")
            return
    except Exception as fallback_err:
        raise Exception(f"Gagal mengunggah file media ke Meta Story Composer: {fallback_err}")

def process_schedule_item(page, item):
    item_id = item.get('id', 'N/A')
    log(f"=== Memulai Penjadwalan Item #{item_id} ===", "PROCESS")
    
    update_db_schedule_status(item_id, 'processing')

    try:
        media_paths = item.get("mediaPaths") or [item["mediaPath"]]
        log(f"Navigasi langsung ke URL Meta Story Composer (/latest/story_composer/)...", "INFO")
        page.goto("https://business.facebook.com/latest/story_composer/", wait_until="domcontentloaded")
        page.wait_for_timeout(4000)

        upload_file_safely(page, media_paths)
        
        log("Menunggu pratinjau media dirender oleh Meta Business Suite...", "INFO")
        page.wait_for_timeout(6000)

        switch_to_jadwalkan_mode(page, max_retries=3, item_id=item_id)

        # 5. PENGISIAN TANGGAL DAN WAKTU (INSPEKSI DOM SPINBUTTON PRESISI)
        log("Tahap 5: Pengisian Tanggal dan Waktu...", "INFO")
        target_date_formatted = format_date_no_padding(item["date"])
        target_time = item["time"]
        
        log(f"Target Tanggal (Format Meta d/m/yyyy): '{target_date_formatted}', Target Waktu: '{target_time}'", "INFO")

        date_inputs = page.locator("input[placeholder='dd/mm/yyyy']").all()
        if len(date_inputs) >= 2:
            fill_input_field(date_inputs[0], target_date_formatted, "Tanggal Facebook")
            fill_input_field(date_inputs[1], target_date_formatted, "Tanggal Instagram")
        elif len(date_inputs) == 1:
            fill_input_field(date_inputs[0], target_date_formatted, "Tanggal Utama")

        fb_time_ok = fill_time_field(page, target_time, section_index=0)
        ig_time_ok = fill_time_field(page, target_time, section_index=1)

        page.wait_for_timeout(2000)

        # 6. KLIK TOMBOL UTAMA 'JADWALKAN' DI KANAN BAWAH
        log("Tahap 6: Mengklik tombol utama 'Jadwalkan' di kanan bawah...", "INFO")
        
        submit_btn = None
        for attempt in range(5):
            candidates = page.locator("button, div[role='button']").all()
            for b in reversed(candidates):
                try:
                    txt = b.inner_text().strip().lower()
                    box = b.bounding_box()
                    is_valid_submit = ('jadwalkan' in txt or 'schedule' in txt) and 'bagikan' not in txt and 'bantuan' not in txt
                    if b.is_visible() and box and box['y'] > 300 and is_valid_submit:
                        submit_btn = b
                        break
                except Exception:
                    pass
            if submit_btn:
                break
            page.wait_for_timeout(1000)

        if submit_btn:
            btn_name = submit_btn.inner_text().strip()
            log(f"Tombol submit 'Jadwalkan' VALID ditemukan ('{btn_name}'). Mengklik...", "SUCCESS")
            submit_btn.click(force=True)
            page.wait_for_timeout(6000)
            
            log(f"=== Item #{item_id} Selesai Dijadwalkan! ===", "SUCCESS")
            update_db_schedule_status(item_id, 'completed')
        else:
            raise Exception("Tombol submit 'Jadwalkan' tidak ditemukan atau belum aktif pada Tahap 6.")

    except Exception as e:
        log(f"Gagal memproses item #{item_id}: {e}", "ERROR")
        update_db_schedule_status(item_id, 'failed', str(e))
        raise e

def main():
    print("""
    ==================================================================
    🤖 META BUSINESS SUITE - INSTAGRAM STORY AUTO SCHEDULER (PYTHON)
    ==================================================================
    """)
    
    config = load_json("config.json")
    schedule = load_json("schedule.json")
    
    # Parse parameter --user_data jika ada untuk Multi-Akun Meta
    user_data_dir_name = config.get("user_data_dir", "./user_data")
    for arg in sys.argv[1:]:
        if arg.startswith("--user_data="):
            user_data_dir_name = arg.split("=")[1]

    user_data_dir = str(Path(user_data_dir_name).resolve())
    headless = config.get("headless", False)
    delay_between = config.get("delay_between_items", 5)

    log(f"Memuat {len(schedule)} item jadwal dari schedule.json", "INFO")
    log(f"Profil User Data Chrome disimpan di: {user_data_dir}", "INFO")

    with sync_playwright() as p:
        log("Membuka browser Chromium dengan sesi login persisten...", "INFO")
        context = p.chromium.launch_persistent_context(
            user_data_dir,
            headless=headless,
            viewport=config.get("viewport", {"width": 1280, "height": 800}),
            args=[
                "--start-maximized", 
                "--disable-blink-features=AutomationControlled",
                "--disable-infobars",
                "--disable-session-crashed-bubble"
            ]
        )

        page = context.pages[0] if context.pages else context.new_page()

        log("Pemeriksaan status login Meta Business Suite...", "INFO")
        page.goto("https://business.facebook.com/latest/home", wait_until="domcontentloaded")
        page.wait_for_timeout(3000)

        if "login" in page.url.lower() or "facebook.com/login" in page.content().lower():
            log("AWAL: Anda belum login ke Meta Business Suite.", "WARN")
            print("\n👉 SILAKAN LOGIN KE AKUN META/FACEBOOK ANDA DI JENDELA BROWSER YANG TERBUKA.")
            print("👉 Setelah selesai login dan berada di Dashboard Meta, tekan [ENTER] di terminal ini untuk melanjutkan...\n")
            input("Tekan [ENTER] jika sudah login...")

        default_portfolio = schedule[0].get("portfolioName") or config.get("default_portfolio", "Sevencols")
        select_portfolio_on_home(page, default_portfolio)

        total = len(schedule)
        success_count = 0

        for idx, item in enumerate(schedule, 1):
            log(f"\n--- Memproses Antrean ({idx}/{total}) ---", "PROCESS")
            try:
                process_schedule_item(page, item)
                success_count += 1
            except Exception as e:
                log(f"Gagal memproses item #{item.get('id')}: {e}", "ERROR")

            if idx < total:
                wait_time = delay_between + random.randint(1, 4)
                log(f"Menunggu {wait_time} detik sebelum memproses item berikutnya...", "INFO")
                time.sleep(wait_time)

        log(f"\n🎉 OTOMATISASI SELESAI! {success_count}/{total} Story berhasil dijadwalkan.", "SUCCESS")
        
        time.sleep(5)
        context.close()

if __name__ == "__main__":
    main()
