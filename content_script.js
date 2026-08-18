/**
 * content_script.js - Bot Manipulasi DOM Meta Business Suite (React-Compatible)
 * 
 * Script ini disuntikkan ke dalam halaman Meta Business Suite untuk melakukan simulasi interaksi pengguna
 * secara otomatis berdasarkan alur:
 * 1. Memilih Portofolio Bisnis (misal: "Sevencols") di pojok kiri atas jika belum aktif.
 * 2. Navigasi KHUSUS ke Halaman "Buat Cerita" (/latest/story_composer/) dan MENGHINDARI "Buat Postingan".
 * 3. Memilih & menyuntikkan file media (gambar/video) ke elemen input file via DataTransfer API.
 * 4. Mengaktifkan tab/pilihan "Jadwalkan".
 * 5. Mengisi Target Tanggal & Waktu (Instagram & Facebook).
 * 6. Mengklik tombol utama "Jadwalkan" di sudut kanan bawah.
 */

console.log("[IG Story Bot] Content script dimuat di Meta Business Suite.");

// ============================================================================
// 1. HELPER MANDATORI REACT (SANGAT PENTING)
// ============================================================================

/**
 * Memasukkan nilai ke dalam elemen input HTML yang dikelola oleh Virtual DOM React.
 * @param {HTMLInputElement|HTMLTextAreaElement} element - Elemen input target DOM
 * @param {string} value - Nilai teks/tanggal/waktu yang ingin dimasukkan
 */
function setReactValue(element, value) {
    if (!element) return;
    const prototype = Object.getPrototypeOf(element);
    const nativeSetter = Object.getOwnPropertyDescriptor(prototype, 'value')?.set 
                      || Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
    
    if (nativeSetter) {
        nativeSetter.call(element, value);
    } else {
        element.value = value;
    }
    
    // Trigger event sintetik agar Virtual DOM React merespons pembaruan state
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
}

// ============================================================================
// 2. HELPER UTILITAS DOM & ASYNCHRONOUS
// ============================================================================

/**
 * Jeda waktu asinkronus (Promise-based delay)
 */
const delay = ms => new Promise(res => setTimeout(res, ms));

/**
 * Menunggu suatu elemen DOM muncul menggunakan teknik polling dengan timeout.
 */
function waitForElement(finderFn, timeoutMs = 15000, intervalMs = 500) {
    return new Promise((resolve, reject) => {
        const startTime = Date.now();
        
        const check = () => {
            try {
                const element = finderFn();
                if (element) {
                    return resolve(element);
                }
            } catch (err) {
                // Abaikan kesalahan minor saat penelusuran DOM
            }

            if (Date.now() - startTime >= timeoutMs) {
                reject(new Error(`Timeout (${timeoutMs}ms) menunggu elemen DOM.`));
            } else {
                setTimeout(check, intervalMs);
            }
        };

        check();
    });
}

/**
 * Format tanggal ISO (YYYY-MM-DD) ke Bahasa Indonesia (misal: "18 Agustus 2026")
 */
function formatIndonesianDate(isoDateStr) {
    if (!isoDateStr) return '';
    const months = [
        "Januari", "Februari", "Maret", "April", "Mei", "Juni",
        "Juli", "Agustus", "September", "Oktober", "November", "Desember"
    ];
    const parts = isoDateStr.split('-');
    if (parts.length === 3) {
        const year = parts[0];
        const monthIndex = parseInt(parts[1], 10) - 1;
        const day = parseInt(parts[2], 10);
        if (monthIndex >= 0 && monthIndex < 12) {
            return `${day} ${months[monthIndex]} ${year}`;
        }
    }
    return isoDateStr;
}

/**
 * Mengunggah file Blob dari URL eksternal dan menginjeksinya ke <input type="file"> via DataTransfer API.
 */
async function fetchAndInjectFile(inputElement, mediaUrl, filename) {
    console.log(`[IG Story Bot] Mengunduh media Blob dari: ${mediaUrl}`);
    const response = await fetch(mediaUrl);
    if (!response.ok) {
        throw new Error(`Gagal mengunduh file media. HTTP status: ${response.status}`);
    }
    
    const blob = await response.blob();
    const file = new File([blob], filename || 'story_media.jpg', { type: blob.type || 'image/jpeg' });
    
    // Injeksi menggunakan DataTransfer API
    const dataTransfer = new DataTransfer();
    dataTransfer.items.add(file);
    inputElement.files = dataTransfer.files;
    
    // Memicu Synthetic Events React
    inputElement.dispatchEvent(new Event('change', { bubbles: true }));
    inputElement.dispatchEvent(new Event('input', { bubbles: true }));
    console.log(`[IG Story Bot] Berhasil menyuntikkan file "${file.name}" (${file.size} bytes) ke dalam input[type="file"].`);
}

// ============================================================================
// 3. MAIN AUTOMATION FLOW (ALUR OTOMATISASI PENJADWALAN)
// ============================================================================

/**
 * Listener Pesan dari Background Service Worker
 */
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.action === 'PROCESS_STORY_ITEM') {
        console.log('[IG Story Bot] Menerima instruksi pemrosesan item:', request.item);

        processStoryScheduleItem(request.item)
            .then(() => {
                sendResponse({ success: true });
            })
            .catch((err) => {
                console.error('[IG Story Bot] Gagal memproses item:', err);
                sendResponse({ success: false, error: err.message });
            });

        return true; // Respons dikirim secara asinkronus
    }
});

/**
 * Alur utama otomatisasi 1 item jadwal IG Story
 */
async function processStoryScheduleItem(item) {
    console.log(`[IG Story Bot] === Memulai Penjadwalan Story: ${item.id} ===`);

    // ------------------------------------------------------------------------
    // TAHAP 1: MEMILIH PORTOFOLIO BISNIS (Sesuai Screenshot 1)
    // ------------------------------------------------------------------------
    if (item.portfolioName) {
        console.log(`[IG Story Bot] Tahap 1: Memeriksa Portofolio Bisnis "${item.portfolioName}"...`);
        await selectPortfolio(item.portfolioName);
    }

    // ------------------------------------------------------------------------
    // TAHAP 2: NAVIGASI KE HALAMAN "BUAT CERITA" (TERISOLASI DARI "BUAT POSTINGAN")
    // ------------------------------------------------------------------------
    // Mencegah masuk ke "Buat postingan" (/latest/composer) dan memastikan masuk ke "Buat cerita" (/latest/story_composer/)
    // ------------------------------------------------------------------------
    console.log('[IG Story Bot] Tahap 2: Mengakses Halaman Composer Story...');

    const currentUrl = window.location.href;
    const bodyText = document.body ? document.body.innerText : '';
    const isPostComposer = bodyText.includes('Buat postingan') || currentUrl.includes('/latest/composer?') || (currentUrl.includes('/latest/composer') && !currentUrl.includes('story_composer'));
    const isStoryComposer = currentUrl.includes('story_composer') || bodyText.includes('Buat cerita');

    if (isPostComposer || !isStoryComposer) {
        console.log('[IG Story Bot] Terdeteksi di halaman "Buat postingan" atau bukan Story Composer! Mencari tombol "Buat cerita" atau redirect ke /latest/story_composer/...');
        
        // Strategi 2a: Cari link atau button persis berpembatas "Buat cerita" (bukan "Buat postingan")
        const createStoryBtn = Array.from(document.querySelectorAll('button, div[role="button"], a')).find(b => {
            const txt = b.textContent ? b.textContent.trim().toLowerCase() : '';
            const isStory = txt === 'buat cerita' || txt === 'create story' || (txt.includes('buat cerita') && !txt.includes('postingan'));
            const isVisible = b.offsetWidth > 0;
            return isStory && isVisible;
        });

        if (createStoryBtn) {
            console.log('[IG Story Bot] Tombol "Buat cerita" ditemukan di DOM. Mengklik tombol...');
            createStoryBtn.click();
            await delay(3500);
        } else {
            console.log('[IG Story Bot] Mengalihkan URL langsung ke URL Story Composer (/latest/story_composer/)...');
            
            // Dapatkan parameter business_id dan asset_id jika ada di URL saat ini
            const urlParams = new URLSearchParams(window.location.search);
            const businessId = urlParams.get('business_id');
            const assetId = urlParams.get('asset_id');
            
            let storyUrl = 'https://business.facebook.com/latest/story_composer/';
            if (businessId || assetId) {
                storyUrl += `?${businessId ? `business_id=${businessId}&` : ''}${assetId ? `asset_id=${assetId}` : ''}`;
            }

            window.location.href = storyUrl;
            await delay(5000);
            return; // Tunggu halaman selesai memuat ulang
        }
    }

    // ------------------------------------------------------------------------
    // TAHAP 3: UNGGAN FILE MEDIA (Sesuai Screenshot 3 - "Tambahkan foto/video" di Halaman Buat cerita)
    // ------------------------------------------------------------------------
    console.log('[IG Story Bot] Tahap 3: Menunggu elemen input[type="file"] pada Halaman Buat cerita...');
    
    const fileInput = await waitForElement(() => {
        const inputs = Array.from(document.querySelectorAll('input[type="file"]'));
        if (inputs.length === 0) return null;
        
        return inputs.find(i => {
            const accept = i.getAttribute('accept') || '';
            return accept.includes('image') || accept.includes('video') || accept === '';
        }) || inputs[0];
    }, 15000);

    console.log('[IG Story Bot] Input file ditemukan. Mengunggah file Blob...');
    await fetchAndInjectFile(fileInput, item.mediaUrl, item.filename);

    // Menunggu preview cerita (Cerita Facebook & Cerita Instagram di kanan) selesai dimuat
    console.log('[IG Story Bot] Menunggu preview media cerita...');
    await delay(6000);

    // ------------------------------------------------------------------------
    // TAHAP 4: AKTIFKAN OPSI "JADWALKAN" (Sesuai Screenshot 3)
    // ------------------------------------------------------------------------
    console.log('[IG Story Bot] Tahap 4: Memilih opsi "Jadwalkan"...');
    
    const schedulePillBtn = await waitForElement(() => {
        const elements = Array.from(document.querySelectorAll('button, div[role="button"], div[role="tab"], label, span'));
        return elements.find(el => {
            const text = el.textContent ? el.textContent.trim().toLowerCase() : '';
            return text === 'jadwalkan' || text === 'schedule';
        });
    }, 10000).catch(() => null);

    if (schedulePillBtn) {
        console.log('[IG Story Bot] Mengklik pill button "Jadwalkan"...');
        schedulePillBtn.click();
        await delay(1500);
    }

    // ------------------------------------------------------------------------
    // TAHAP 5: ISI TARGET TANGGAL & WAKTU (Sesuai Screenshot 3)
    // ------------------------------------------------------------------------
    console.log('[IG Story Bot] Tahap 5: Pengisian Input Tanggal dan Waktu...');

    const dateFormatted = formatIndonesianDate(item.date); // e.g. "18 Agustus 2026"
    console.log(`[IG Story Bot] Target Tanggal: ${item.date} (${dateFormatted}), Waktu: ${item.time}`);

    const allInputs = Array.from(document.querySelectorAll('input'));
    
    const dateInputs = allInputs.filter(i => {
        const type = (i.getAttribute('type') || '').toLowerCase();
        const ph = (i.getAttribute('placeholder') || '').toLowerCase();
        const label = (i.getAttribute('aria-label') || '').toLowerCase();
        const value = (i.value || '').toLowerCase();
        return type === 'date' || ph.includes('yyyy') || ph.includes('dd') || label.includes('tanggal') || label.includes('date') || value.includes('agustus') || value.includes('202');
    });

    const timeInputs = allInputs.filter(i => {
        const type = (i.getAttribute('type') || '').toLowerCase();
        const ph = (i.getAttribute('placeholder') || '').toLowerCase();
        const label = (i.getAttribute('aria-label') || '').toLowerCase();
        const value = (i.value || '').toLowerCase();
        return type === 'time' || ph.includes('hh:mm') || label.includes('waktu') || label.includes('jam') || label.includes('time') || value.includes(':');
    });

    for (const dInput of dateInputs) {
        dInput.focus();
        setReactValue(dInput, item.date);
        setReactValue(dInput, dateFormatted);
        dInput.blur();
        await delay(500);
    }

    for (const tInput of timeInputs) {
        tInput.focus();
        setReactValue(tInput, item.time);
        tInput.blur();
        await delay(500);
    }

    await delay(1500);

    // ------------------------------------------------------------------------
    // TAHAP 6: KLIK TOMBOL UTAMA "JADWALKAN" (Sesuai Screenshot 3 - Kanan Bawah)
    // ------------------------------------------------------------------------
    console.log('[IG Story Bot] Tahap 6: Mengklik tombol utama "Jadwalkan" di kanan bawah...');

    const submitBtn = await waitForElement(() => {
        const buttons = Array.from(document.querySelectorAll('button'));
        return buttons.find(b => {
            const txt = b.textContent ? b.textContent.trim().toLowerCase() : '';
            const isSubmit = txt === 'jadwalkan' || txt === 'schedule';
            const isEnabled = !b.disabled && b.getAttribute('aria-disabled') !== 'true';
            const rect = b.getBoundingClientRect();
            return isSubmit && isEnabled && rect.top > 300;
        });
    }, 12000);

    console.log('[IG Story Bot] Mengklik tombol penyerahan "Jadwalkan"...');
    submitBtn.click();

    // ------------------------------------------------------------------------
    // TAHAP 7: KONFIRMASI & TUNGGU PROSES SELESAI
    // ------------------------------------------------------------------------
    console.log('[IG Story Bot] Tahap 7: Menunggu respons konfirmasi Meta Business Suite...');
    await delay(6000);

    console.log(`[IG Story Bot] === Item ${item.id} Berhasil Dijadwalkan ===`);
}

/**
 * Helper Fungsi memilih Portofolio Bisnis di Pojok Kiri Atas (Sesuai Screenshot 1)
 */
async function selectPortfolio(targetPortfolioName) {
    if (!targetPortfolioName) return;

    const brandElement = document.querySelector('header, nav, [role="navigation"]');
    if (brandElement && brandElement.textContent.toLowerCase().includes(targetPortfolioName.toLowerCase())) {
        console.log(`[IG Story Bot] Portofolio "${targetPortfolioName}" sudah aktif.`);
        return;
    }

    const portfolioDropdownBtn = await waitForElement(() => {
        const buttons = Array.from(document.querySelectorAll('button, div[role="button"]'));
        return buttons.find(b => {
            const rect = b.getBoundingClientRect();
            return rect.left < 350 && rect.top < 150 && rect.width > 40;
        });
    }, 6000).catch(() => null);

    if (portfolioDropdownBtn) {
        console.log('[IG Story Bot] Mengklik menu dropdown portofolio kiri atas...');
        portfolioDropdownBtn.click();
        await delay(1500);

        const portfolioOption = await waitForElement(() => {
            const elements = Array.from(document.querySelectorAll('div, span, button, [role="option"]'));
            return elements.find(el => {
                const text = el.textContent ? el.textContent.trim().toLowerCase() : '';
                return text === targetPortfolioName.toLowerCase() || text.includes(targetPortfolioName.toLowerCase());
            });
        }, 8000).catch(() => null);

        if (portfolioOption) {
            console.log(`[IG Story Bot] Memilih portofolio "${targetPortfolioName}"...`);
            portfolioOption.click();
            await delay(4000);
        }
    }
}
