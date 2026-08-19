<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Meta Story Auto Scheduler') - Web UI</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            background-color: #0f172a;
            color: #f8fafc;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        .card-dark {
            background-color: #1e293b;
            border-color: #334155;
        }
        /* Custom SweetAlert Dark Theme */
        .swal2-popup-dark {
            background-color: #1e293b !important;
            color: #f8fafc !important;
            border: 1px solid #334155 !important;
            border-radius: 1rem !important;
        }
        .swal2-title-dark {
            color: #ffffff !important;
        }
        .swal2-html-dark {
            color: #94a3b8 !important;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between relative">

    <!-- Header Navigation -->
    <nav class="bg-gray-900/90 backdrop-blur-md border-b border-gray-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo & Brand -->
                <div class="flex items-center space-x-3">
                    <a href="{{ route('projects.index') }}" class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-tr from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fa-solid fa-robot text-lg"></i>
                        </div>
                        <div>
                            <span class="font-bold text-lg text-white tracking-wide block leading-tight">Meta Story Auto Scheduler</span>
                            <span class="text-[11px] text-gray-400 font-medium">Rolling 29-Day Campaign Manager</span>
                        </div>
                    </a>
                </div>

                <!-- Navigation Tabs -->
                <div class="hidden md:flex items-center space-x-2">
                    <a href="{{ route('projects.index') }}" 
                       class="px-4 py-2 text-xs font-semibold rounded-xl transition flex items-center space-x-2 {{ request()->routeIs('projects.*') ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">
                        <i class="fa-solid fa-folder-open"></i>
                        <span>Projects</span>
                    </a>

                    <a href="{{ route('schedules.index') }}" 
                       class="px-4 py-2 text-xs font-semibold rounded-xl transition flex items-center space-x-2 {{ request()->routeIs('schedules.*') ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">
                        <i class="fa-solid fa-calendar-days"></i>
                        <span>Antrean Story</span>
                    </a>

                    <a href="{{ route('meta-accounts.index') }}" 
                       class="px-4 py-2 text-xs font-semibold rounded-xl transition flex items-center space-x-2 {{ request()->routeIs('meta-accounts.*') ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">
                        <i class="fa-solid fa-users"></i>
                        <span>Akun Meta</span>
                    </a>
                </div>

                <!-- Action Header Buttons -->
                <div class="flex items-center space-x-3">
                    <button id="btnFetchPortfolios" 
                            class="px-3.5 py-2 text-xs font-semibold rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-200 border border-gray-700 transition flex items-center space-x-2 shadow">
                        <i class="fa-solid fa-arrows-rotate text-indigo-400"></i>
                        <span>Ambil Portofolio</span>
                    </button>

                    <button id="btnSyncRunBot" 
                            class="px-4 py-2 text-xs font-semibold rounded-lg bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white shadow-md transition flex items-center space-x-2">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Sync & Jalankan Bot</span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @yield('content')
    </main>

    <!-- Global Footer -->
    <footer class="bg-gray-900 border-t border-gray-800 py-6 text-center text-xs text-gray-500">
        <p>&copy; {{ date('Y') }} Meta Story Auto Scheduler — Multi-Account & Rolling 29-Day Campaign Manager</p>
    </footer>

    <!-- Floating Live Progress Widget Kanan Bawah -->
    <div id="botProgressWidget" class="fixed bottom-6 right-6 z-50 hidden max-w-sm w-full transition-all duration-300">
        <div id="widgetContainer" class="bg-gray-900/95 backdrop-blur-xl border border-indigo-500/40 rounded-2xl p-4 shadow-2xl space-y-3 relative overflow-hidden">
            <!-- Top Header Widget -->
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2.5">
                    <div class="relative flex h-3 w-3" id="widgetStatusDot">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </div>
                    <span class="text-xs font-bold text-white tracking-wide uppercase">EKSEKUSI BOT PLAYWRIGHT</span>
                </div>
                <button onclick="hideProgressWidget()" class="text-gray-400 hover:text-white transition text-sm">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Main Status Message -->
            <p class="text-xs text-indigo-300 font-medium" id="widgetStatusText">
                Memicu bot Playwright...
            </p>

            <!-- Dynamic Progress Bar -->
            <div class="w-full bg-gray-800 rounded-full h-2 overflow-hidden border border-gray-700">
                <div id="widgetProgressBar" class="bg-gradient-to-r from-indigo-500 to-purple-500 h-2 rounded-full transition-all duration-500" style="width: 0%"></div>
            </div>

            <!-- Stats Counts Grid (Batch Murni Sesi Ini) -->
            <div class="grid grid-cols-3 gap-2 text-center text-[11px] pt-1">
                <div class="bg-gray-800/80 p-2 rounded-xl border border-gray-700/60">
                    <span class="text-amber-400 font-bold block text-sm" id="widgetPendingCount">0</span>
                    <span class="text-gray-400 text-[10px]">Pending</span>
                </div>
                <div class="bg-gray-800/80 p-2 rounded-xl border border-gray-700/60">
                    <span class="text-emerald-400 font-bold block text-sm" id="widgetCompletedCount">0</span>
                    <span class="text-gray-400 text-[10px]">Selesai (Batch)</span>
                </div>
                <div class="bg-gray-800/80 p-2 rounded-xl border border-gray-700/60">
                    <span class="text-red-400 font-bold block text-sm" id="widgetFailedCount">0</span>
                    <span class="text-gray-400 text-[10px]">Gagal (Batch)</span>
                </div>
            </div>

            <!-- Detailed Error Trace Box (Jika Terjadi Error) -->
            <div id="widgetErrorBox" class="hidden text-[11px] text-red-300 bg-red-950/60 p-3 rounded-xl border border-red-800 space-y-1">
                <div class="font-bold flex items-center space-x-1.5 text-red-400">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Detail Error Eksekusi Bot:</span>
                </div>
                <p class="font-mono text-[10px] text-red-200/90 break-words" id="widgetErrorDetail"></p>
            </div>

            <!-- Latest Activity Log -->
            <div class="text-[10px] text-gray-400 bg-gray-950/60 p-2 rounded-lg border border-gray-800 font-mono truncate" id="widgetLatestNote">
                Menunggu respon bot...
            </div>
        </div>
    </div>

    <!-- Global SweetAlert & AJAX Scripts -->
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let progressInterval = null;
        let batchInitialPending = 0;
        let initialCompletedOffset = null;
        let initialFailedOffset = null;

        function showProgressWidget() {
            document.getElementById('botProgressWidget').classList.remove('hidden');
        }

        function hideProgressWidget() {
            document.getElementById('botProgressWidget').classList.add('hidden');
            if (progressInterval) clearInterval(progressInterval);
        }

        function showDetailedErrorModal(itemCode, errorText) {
            Swal.fire({
                icon: 'error',
                title: `Detail Error Item (${itemCode})`,
                html: `<div class="text-left font-mono text-xs bg-gray-950 p-4 rounded-xl text-red-300 border border-red-900 overflow-x-auto max-h-60 leading-relaxed">${errorText}</div>`,
                confirmButtonText: 'Tutup',
                customClass: {
                    popup: 'swal2-popup-dark',
                    title: 'swal2-title-dark',
                    htmlContainer: 'swal2-html-dark'
                }
            });
        }

        function startProgressPolling(initialCount) {
            batchInitialPending = initialCount || 0;
            initialCompletedOffset = null;
            initialFailedOffset = null;

            showProgressWidget();
            if (progressInterval) clearInterval(progressInterval);

            document.getElementById('widgetProgressBar').style.width = '0%';
            document.getElementById('widgetPendingCount').textContent = batchInitialPending;
            document.getElementById('widgetCompletedCount').textContent = 0;
            document.getElementById('widgetFailedCount').textContent = 0;
            document.getElementById('widgetStatusText').textContent = `🚀 Memproses ${batchInitialPending} item PENDING...`;
            document.getElementById('widgetErrorBox').classList.add('hidden');
            document.getElementById('widgetContainer').className = "bg-gray-900/95 backdrop-blur-xl border border-indigo-500/40 rounded-2xl p-4 shadow-2xl space-y-3 relative overflow-hidden";

            progressInterval = setInterval(() => {
                fetch("{{ route('schedules.executionProgress') }}")
                .then(res => res.json())
                .then(data => {
                    if (initialCompletedOffset === null) {
                        initialCompletedOffset = data.completed;
                        initialFailedOffset = data.failed;
                    }

                    const batchCompleted = Math.max(0, data.completed - initialCompletedOffset);
                    const batchFailed = Math.max(0, data.failed - initialFailedOffset);

                    document.getElementById('widgetPendingCount').textContent = data.pending;
                    document.getElementById('widgetCompletedCount').textContent = batchCompleted;
                    document.getElementById('widgetFailedCount').textContent = batchFailed;

                    if (data.latest_note) {
                        document.getElementById('widgetLatestNote').textContent = data.latest_note;
                    }

                    // Tampilkan Error Box jika ada item batch yang FAILED
                    if (batchFailed > 0 && data.failed_note) {
                        document.getElementById('widgetErrorBox').classList.remove('hidden');
                        document.getElementById('widgetErrorDetail').textContent = `[${data.failed_item}] ${data.failed_note}`;
                        document.getElementById('widgetContainer').className = "bg-gray-900/95 backdrop-blur-xl border border-red-500/80 rounded-2xl p-4 shadow-2xl space-y-3 relative overflow-hidden shadow-red-950/50";
                    }

                    if (batchInitialPending > 0) {
                        const processedInBatch = batchCompleted + batchFailed;
                        const pct = Math.min(100, Math.round((processedInBatch / batchInitialPending) * 100));
                        document.getElementById('widgetProgressBar').style.width = `${pct}%`;
                    }

                    if (data.pending > 0) {
                        document.getElementById('widgetStatusText').textContent = `🚀 Memproses antrean... Tersisa ${data.pending} item PENDING`;
                    } else {
                        if (batchFailed > 0) {
                            document.getElementById('widgetStatusText').textContent = `⚠️ Selesai dengan ${batchFailed} item GAGAL dalam batch ini.`;
                        } else {
                            document.getElementById('widgetStatusText').textContent = `✅ Seluruh ${batchInitialPending} antrean PENDING selesai diproses!`;
                            document.getElementById('widgetProgressBar').style.width = `100%`;
                            setTimeout(() => {
                                hideProgressWidget();
                                window.location.reload();
                            }, 4000);
                        }
                    }
                })
                .catch(err => {
                    console.log('Progress check error:', err);
                });
            }, 3000);
        }

        // Global Alert Helper
        function showAlert(icon, title, text) {
            Swal.fire({
                icon: icon,
                title: title,
                text: text,
                customClass: {
                    popup: 'swal2-popup-dark',
                    title: 'swal2-title-dark',
                    htmlContainer: 'swal2-html-dark'
                }
            });
        }

        // Action: Ambil Portofolio Meta
        document.getElementById('btnFetchPortfolios')?.addEventListener('click', function() {
            Swal.fire({
                title: 'Memindai Aset Meta...',
                text: 'Membuka bot Chromium untuk mengambil Aset Bisnis Meta...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
                customClass: { popup: 'swal2-popup-dark', title: 'swal2-title-dark', htmlContainer: 'swal2-html-dark' }
            });

            fetch("{{ route('portfolios.fetch') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Berhasil Dipindai!', data.message);
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    showAlert('info', 'Info Pemindaian', data.message);
                }
            })
            .catch(err => {
                showAlert('error', 'Kesalahan Sistem', err.message || 'Gagal memindai portofolio.');
            });
        });

        // Action: Sync & Jalankan Bot
        document.getElementById('btnSyncRunBot')?.addEventListener('click', function() {
            fetch("{{ route('schedules.syncRun') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Bot Berhasil Dicu!', data.message);
                    startProgressPolling(data.count || 0);
                } else {
                    showAlert('info', 'Status Antrean Schedule', data.message);
                }
            })
            .catch(err => {
                showAlert('error', 'Kesalahan Sistem', err.message || 'Gagal mengeksekusi bot.');
            });
        });
    </script>
    @yield('scripts')
</body>
</html>
