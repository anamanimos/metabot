/**
 * background.js - Service Worker Ekstensi Manifest V3
 * Mengelola state otomatisasi, koordinasi antrean, dan komunikasi antar tab dengan content script.
 */

// Listener pesan dari popup.js atau content_script.js
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (request.action === 'START_AUTOMATION') {
    handleStartAutomation(sendResponse);
    return true; // Keep channel open for async response
  } else if (request.action === 'PAUSE_AUTOMATION') {
    handlePauseAutomation(sendResponse);
    return true;
  } else if (request.action === 'RESET_AUTOMATION') {
    handleResetAutomation(sendResponse);
    return true;
  }
});

/**
 * Memulai / Memelanjutkan alur otomatisasi antrean
 */
async function handleStartAutomation(sendResponse) {
  try {
    const data = await chrome.storage.local.get(['queue', 'currentIndex', 'status']);
    const queue = data.queue || [];
    let currentIndex = data.currentIndex || 0;

    if (queue.length === 0) {
      logToStorage('[WARN] Antrean kosong. Tidak ada yang bisa dijalankan.');
      sendResponse({ success: false, message: 'Antrean kosong' });
      return;
    }

    if (currentIndex >= queue.length) {
      logToStorage('[INFO] Semua jadwal dalam antrean sudah selesai dikerjakan.');
      await chrome.storage.local.set({ status: 'COMPLETED' });
      sendResponse({ success: true, message: 'Antrean selesai' });
      return;
    }

    await chrome.storage.local.set({ status: 'RUNNING' });
    logToStorage(`[SYSTEM] Otomatisasi dimulai dari item #${currentIndex + 1} dari ${queue.length}...`);

    sendResponse({ success: true });
    
    // Jalankan siklus eksekusi item berikutnya
    processNextItem();

  } catch (err) {
    logToStorage(`[ERROR] Gagal memulai otomatisasi: ${err.message}`);
    sendResponse({ success: false, error: err.message });
  }
}

/**
 * Menghentikan sementara alur otomatisasi
 */
async function handlePauseAutomation(sendResponse) {
  await chrome.storage.local.set({ status: 'PAUSED' });
  logToStorage('[PAUSED] Otomatisasi di-jeda oleh pengguna.');
  sendResponse({ success: true });
}

/**
 * Reset antrean
 */
async function handleResetAutomation(sendResponse) {
  await chrome.storage.local.set({ status: 'IDLE', queue: [], currentIndex: 0, logs: [] });
  sendResponse({ success: true });
}

/**
 * Memproses item antrean saat ini
 */
async function processNextItem() {
  const data = await chrome.storage.local.get(['queue', 'currentIndex', 'status']);
  const queue = data.queue || [];
  let currentIndex = data.currentIndex || 0;
  const status = data.status;

  // Cek apakah otomatisasi dibatalkan/dijeda
  if (status !== 'RUNNING') {
    logToStorage(`[SYSTEM] Otomatisasi di-hentikan/jeda saat status: ${status}`);
    return;
  }

  // Cek jika antrean sudah selesai
  if (currentIndex >= queue.length) {
    await chrome.storage.local.set({ status: 'COMPLETED' });
    logToStorage('[SUCCESS] 🎉 SELAMAT! Seluruh antrean IG Story berhasil dijadwalkan!');
    return;
  }

  const currentItem = queue[currentIndex];
  logToStorage(`[PROCESSING] Item #${currentIndex + 1}/${queue.length}: Tgl ${currentItem.date} Jam ${currentItem.time}`);

  // Update item status menjadi 'processing'
  queue[currentIndex].status = 'processing';
  await chrome.storage.local.set({ queue });

  // Cari atau buka tab Meta Business Suite Story Composer
  const tab = await getOrCreateMetaTab();
  if (!tab) {
    logToStorage('[ERROR] Gagal menemukan/membuka tab Meta Business Suite.');
    queue[currentIndex].status = 'error';
    await chrome.storage.local.set({ queue, status: 'ERROR' });
    return;
  }

  // Kirim perintah ke content_script.js pada tab Meta
  chrome.tabs.sendMessage(tab.id, {
    action: 'PROCESS_STORY_ITEM',
    item: currentItem
  }, async (response) => {
    // Penanganan error komunikasi runtime
    if (chrome.runtime.lastError) {
      logToStorage(`[ERROR] Komunikasi tab gagal: ${chrome.runtime.lastError.message}. Memastikan content_script terinjeksi...`);
      
      // Coba inject script secara dinamis jika belum dimuat
      try {
        await chrome.scripting.executeScript({
          target: { tabId: tab.id },
          files: ['content_script.js']
        });
        logToStorage('[INFO] Script berhasil diinjeksi ulang. Mencoba kembali item ini...');
        setTimeout(() => processNextItem(), 3000);
      } catch (e) {
        logToStorage(`[ERROR] Gagal meng-injeksi content_script: ${e.message}`);
        queue[currentIndex].status = 'error';
        await chrome.storage.local.set({ queue, status: 'ERROR' });
      }
      return;
    }

    if (response && response.success) {
      logToStorage(`[SUCCESS] Item #${currentIndex + 1} berhasil dijadwalkan di Meta Business Suite!`);
      queue[currentIndex].status = 'done';
      currentIndex++;
      await chrome.storage.local.set({ queue, currentIndex });

      // Jeda acak 3-8 detik sebelum item berikutnya (humanize delay)
      const randomWait = Math.floor(Math.random() * (8000 - 3000 + 1)) + 3000;
      logToStorage(`[WAIT] Menunggu ${Math.round(randomWait / 1000)} detik sebelum item berikutnya...`);
      
      setTimeout(() => {
        processNextItem();
      }, randomWait);

    } else {
      const errorMsg = (response && response.error) ? response.error : 'Terjadi kegagalan yang tidak diketahui';
      logToStorage(`[ERROR] Gagal memproses item #${currentIndex + 1}: ${errorMsg}`);
      queue[currentIndex].status = 'error';
      currentIndex++;
      await chrome.storage.local.set({ queue, currentIndex });

      // Lanjut ke item berikutnya setelah jeda singkat
      setTimeout(() => {
        processNextItem();
      }, 4000);
    }
  });
}

/**
 * Mencari tab Meta Business Suite yang sudah terbuka atau membuat tab baru khusus ke Story Composer
 */
async function getOrCreateMetaTab() {
  const tabs = await chrome.tabs.query({ url: ["https://business.facebook.com/*", "https://web.facebook.com/*"] });
  if (tabs.length > 0) {
    const activeTab = tabs[0];
    await chrome.tabs.update(activeTab.id, { active: true });
    return activeTab;
  }

  // URL Khusus Story Composer Meta Business Suite (/latest/story_composer/)
  const newTab = await chrome.tabs.create({
    url: 'https://business.facebook.com/latest/story_composer/'
  });

  // Menunggu tab selesai dimuat
  await new Promise(resolve => {
    chrome.tabs.onUpdated.addListener(function listener(tabId, info) {
      if (tabId === newTab.id && info.status === 'complete') {
        chrome.tabs.onUpdated.removeListener(listener);
        resolve();
      }
    });
  });

  return newTab;
}

/**
 * Helper untuk menambahkan pesan log ke chrome.storage.local
 */
function logToStorage(message) {
  const timestamp = new Date().toLocaleTimeString();
  const formatted = `[${timestamp}] ${message}`;
  chrome.storage.local.get(['logs'], (res) => {
    const logs = res.logs || [];
    logs.push(formatted);
    chrome.storage.local.set({ logs });
  });
}
