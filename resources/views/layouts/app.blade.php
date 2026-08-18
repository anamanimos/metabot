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
<body class="min-h-screen flex flex-col justify-between">

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

    <!-- Global SweetAlert & AJAX Scripts -->
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

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

        // Global Loading Alert Helper
        function showLoading(title, htmlText) {
            Swal.fire({
                title: title,
                html: htmlText,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                customClass: {
                    popup: 'swal2-popup-dark',
                    title: 'swal2-title-dark',
                    htmlContainer: 'swal2-html-dark'
                }
            });
        }

        // Action: Ambil Portofolio Meta
        document.getElementById('btnFetchPortfolios')?.addEventListener('click', function() {
            showLoading('Memindai Aset Meta...', 'Membuka bot Chromium untuk mengambil Aset Bisnis Meta...');

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
            showLoading('Mengekspor & Membuka Bot...', 'Menyiapkan schedule.json dan memicu eksekusi bot Playwright...');

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
                    showAlert('success', 'Bot Eksekusi Berjalan!', data.message);
                    setTimeout(() => window.location.reload(), 2000);
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
