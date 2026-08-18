/**
 * popup.js - Controller UI Popup Ekstensi
 * Mengatur interaksi pengguna, pembacaan file JSON, dan sinkronisasi state dengan storage & background script.
 */

document.addEventListener('DOMContentLoaded', () => {
  // DOM Elements
  const jsonFileInput = document.getElementById('jsonFileInput');
  const btnDownloadSample = document.getElementById('btnDownloadSample');
  const btnStart = document.getElementById('btnStart');
  const btnPause = document.getElementById('btnPause');
  const btnReset = document.getElementById('btnReset');
  const btnClearLog = document.getElementById('btnClearLog');
  const statusBadge = document.getElementById('statusBadge');
  const summarySection = document.getElementById('summarySection');
  const totalItemsCount = document.getElementById('totalItemsCount');
  const progressCount = document.getElementById('progressCount');
  const progressBar = document.getElementById('progressBar');
  const itemList = document.getElementById('itemList');
  const logConsole = document.getElementById('logConsole');

  // Inisialisasi State dari Storage
  loadStateFromStorage();

  // Listener Perubahan Storage (Real-time updates dari background script)
  chrome.storage.onChanged.addListener((changes, namespace) => {
    if (namespace === 'local') {
      loadStateFromStorage();
    }
  });

  // 1. Upload File JSON
  jsonFileInput.addEventListener('change', (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async (e) => {
      try {
        const data = JSON.parse(e.target.result);
        if (!Array.isArray(data) || data.length === 0) {
          throw new Error('File JSON harus berupa Array object dan tidak boleh kosong.');
        }

        // Format data antrean
        const queue = data.map((item, index) => ({
          id: item.id || `story_${index + 1}_${Date.now()}`,
          portfolioName: item.portfolioName || '',
          mediaUrl: item.mediaUrl || '',
          filename: item.filename || `story_${index + 1}.jpg`,
          date: item.date || '', // Format YYYY-MM-DD
          time: item.time || '', // Format HH:mm
          notes: item.notes || '',
          status: 'pending' // 'pending' | 'processing' | 'done' | 'error'
        }));

        // Validasi elemen wajib
        const invalidItem = queue.find(i => !i.mediaUrl || !i.date || !i.time);
        if (invalidItem) {
          throw new Error('Setiap item di JSON wajib memiliki field: "mediaUrl", "date", dan "time".');
        }

        // Simpan ke storage local
        await chrome.storage.local.set({
          queue: queue,
          currentIndex: 0,
          status: 'IDLE',
          logs: [`[INFO] Berhasil memuat ${queue.length} item dari file JSON.`]
        });

        appendLog(`[SUCCESS] File "${file.name}" berhasil dimuat (${queue.length} item).`, 'success');
      } catch (err) {
        alert(`Gagal membaca file JSON: ${err.message}`);
        appendLog(`[ERROR] ${err.message}`, 'error');
      }
    };
    reader.readAsText(file);
  });

  // 2. Tombol Download Sample JSON
  btnDownloadSample.addEventListener('click', () => {
    const sampleData = [
      {
        id: "story_001",
        portfolioName: "Sevencols",
        mediaUrl: "https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=1080&h=1920&fit=crop",
        filename: "story1.jpg",
        date: "2026-08-20",
        time: "14:30",
        notes: "Contoh Story Promo Produk Sevencols"
      },
      {
        id: "story_002",
        portfolioName: "Sevencols",
        mediaUrl: "https://images.unsplash.com/photo-1517841905240-472988babdf9?w=1080&h=1920&fit=crop",
        filename: "story2.jpg",
        date: "2026-08-21",
        time: "09:15",
        notes: "Contoh Story Articles Sevencols"
      }
    ];

    const blob = new Blob([JSON.stringify(sampleData, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sample_schedule.json';
    a.click();
    URL.revokeObjectURL(url);
  });

  // 3. Tombol Mulai Otomatisasi
  btnStart.addEventListener('click', () => {
    chrome.runtime.sendMessage({ action: 'START_AUTOMATION' }, (res) => {
      if (res && res.success) {
        appendLog('[SYSTEM] Perintah Mulai Otomatisasi dikirim.', 'info');
      }
    });
  });

  // 4. Tombol Jeda
  btnPause.addEventListener('click', () => {
    chrome.runtime.sendMessage({ action: 'PAUSE_AUTOMATION' }, (res) => {
      if (res && res.success) {
        appendLog('[SYSTEM] Perintah Jeda dikirim.', 'warn');
      }
    });
  });

  // 5. Tombol Reset
  btnReset.addEventListener('click', async () => {
    if (confirm('Apakah Anda yakin ingin menghapus antrean jadwal saat ini?')) {
      chrome.runtime.sendMessage({ action: 'RESET_AUTOMATION' }, async () => {
        await chrome.storage.local.clear();
        appendLog('[SYSTEM] Antrean berhasil dibersihkan.', 'info');
      });
    }
  });

  // 6. Tombol Clear Log
  btnClearLog.addEventListener('click', async () => {
    await chrome.storage.local.set({ logs: [] });
    logConsole.innerHTML = '';
  });

  // Function helper untuk memuat dan merender State dari storage
  async function loadStateFromStorage() {
    const data = await chrome.storage.local.get(['queue', 'currentIndex', 'status', 'logs']);
    const queue = data.queue || [];
    const currentIndex = data.currentIndex || 0;
    const status = data.status || 'IDLE';
    const logs = data.logs || [];

    // Render Badge Status
    statusBadge.textContent = status;
    statusBadge.className = `badge badge-${status.toLowerCase()}`;

    // Render Section Antrean
    if (queue.length > 0) {
      summarySection.classList.remove('hidden');
      totalItemsCount.textContent = queue.length;
      progressCount.textContent = `${currentIndex} / ${queue.length}`;
      
      const percent = Math.round((currentIndex / queue.length) * 100);
      progressBar.style.width = `${percent}%`;

      // Render Item Rows
      itemList.innerHTML = queue.map((item, idx) => `
        <div class="item-row ${item.status}">
          <span>#${idx + 1} ${item.portfolioName ? `[${item.portfolioName}]` : ''} 📅 ${item.date} ⏰ ${item.time}</span>
          <strong>[${item.status.toUpperCase()}]</strong>
        </div>
      `).join('');

      btnStart.disabled = status === 'RUNNING' || currentIndex >= queue.length;
      btnPause.classList.toggle('hidden', status !== 'RUNNING');
      btnReset.disabled = false;
    } else {
      summarySection.classList.add('hidden');
      btnStart.disabled = true;
      btnPause.classList.add('hidden');
      btnReset.disabled = true;
    }

    // Render Log Messages
    if (logs.length > 0) {
      logConsole.innerHTML = logs.map(msg => {
        let typeClass = 'log-info';
        if (msg.includes('[SUCCESS]')) typeClass = 'log-success';
        if (msg.includes('[WARN]') || msg.includes('[PAUSED]')) typeClass = 'log-warn';
        if (msg.includes('[ERROR]')) typeClass = 'log-error';
        return `<div class="log-entry ${typeClass}">${escapeHtml(msg)}</div>`;
      }).join('');
      logConsole.scrollTop = logConsole.scrollHeight;
    }
  }

  function appendLog(message, type = 'info') {
    const timestamp = new Date().toLocaleTimeString();
    const formatted = `[${timestamp}] ${message}`;
    chrome.storage.local.get(['logs'], (res) => {
      const logs = res.logs || [];
      logs.push(formatted);
      chrome.storage.local.set({ logs });
    });
  }

  function escapeHtml(text) {
    return text.replace(/[&<>"']/g, function(m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
  }
});
