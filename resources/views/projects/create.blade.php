@extends('layouts.app')

@section('title', 'Buat Project Campaign Baru')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Breadcrumb -->
    <div class="flex items-center space-x-2 text-xs text-gray-400">
        <a href="{{ route('projects.index') }}" class="hover:text-white transition flex items-center space-x-1">
            <i class="fa-solid fa-folder-open"></i>
            <span>Project Campaigns</span>
        </a>
        <span>/</span>
        <span class="text-white font-semibold">Buat Project Baru</span>
    </div>

    <!-- Main Card Form -->
    <div class="card-dark rounded-2xl p-6 sm:p-8 shadow-2xl border border-gray-800 space-y-6">
        <div class="border-b border-gray-800 pb-4">
            <h1 class="text-2xl font-bold text-white flex items-center space-x-3">
                <div class="p-2.5 bg-gradient-to-tr from-indigo-600 to-purple-600 rounded-xl text-white text-lg">
                    <i class="fa-solid fa-plus"></i>
                </div>
                <span>Buat Project Campaign Baru</span>
            </h1>
            <p class="text-sm text-gray-400 mt-1">
                Pilih moda pengulangan **Kontinu**, **1x Post**, atau **Sampai Tanggal Tertentu**.
            </p>
        </div>

        <form id="formCreateProject" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="images_per_post" value="1">

            <!-- Nama Project -->
            <div>
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Nama Project / Campaign</label>
                <input type="text" name="name" required placeholder="Contoh: Project Pagi (Promo & Quotes)" 
                       class="w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 transition">
            </div>

            <!-- Akun Meta & Aset Bisnis Target (Dynamic Dependent Dropdown) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Akun Meta</label>
                    <select name="meta_account_id" id="selectMetaAccount" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 transition">
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->account_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Aset Bisnis Target</label>
                    <select name="portfolio_name" id="selectPortfolio" required class="w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 transition">
                        @foreach($portfolios as $p)
                            <option value="{{ $p->name }}" data-account="{{ $p->meta_account_id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Tipe Pengulangan Project (Repeat Mode) -->
            <div class="space-y-3">
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider">Mode Pengulangan Posting (Repeat Mode)</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                    <label class="flex items-start space-x-3 bg-gray-900 p-4 rounded-xl border border-gray-700 cursor-pointer hover:border-indigo-500 transition">
                        <input type="radio" name="repeat_type" value="continuous" checked class="mt-0.5 text-indigo-600 focus:ring-indigo-500" onchange="toggleRepeatFields()">
                        <div>
                            <strong class="text-white block font-semibold">♾️ Kontinu Selamanya</strong>
                            <span class="text-gray-400 text-[11px]">Terus tayang dengan rolling buffer 29 hari otomatis.</span>
                        </div>
                    </label>

                    <label class="flex items-start space-x-3 bg-gray-900 p-4 rounded-xl border border-gray-700 cursor-pointer hover:border-indigo-500 transition">
                        <input type="radio" name="repeat_type" value="once" class="mt-0.5 text-indigo-600 focus:ring-indigo-500" onchange="toggleRepeatFields()">
                        <div>
                            <strong class="text-white block font-semibold">🎯 Hanya 1x Post</strong>
                            <span class="text-gray-400 text-[11px]">Posting 1 kali saja pada tanggal yang dipilih.</span>
                        </div>
                    </label>

                    <label class="flex items-start space-x-3 bg-gray-900 p-4 rounded-xl border border-gray-700 cursor-pointer hover:border-indigo-500 transition">
                        <input type="radio" name="repeat_type" value="until_date" class="mt-0.5 text-indigo-600 focus:ring-indigo-500" onchange="toggleRepeatFields()">
                        <div>
                            <strong class="text-white block font-semibold">📅 Sampai Tanggal Tertentu</strong>
                            <span class="text-gray-400 text-[11px]">Berulang harian dan otomatis berhenti pada tanggal akhir.</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Dynamic Date Inputs (Single Date vs Range End Date) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="dateInputsContainer">
                <div id="startDateWrapper" class="hidden">
                    <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2" id="startDateLabel">Tanggal Tayang</label>
                    <input type="date" name="start_date" value="{{ date('Y-m-d') }}" 
                           class="w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-indigo-500 transition">
                </div>

                <div id="endDateWrapper" class="hidden">
                    <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Tanggal Berakhir (Dihentikan)</label>
                    <input type="date" name="end_date" value="{{ date('Y-m-d', strtotime('+30 days')) }}" 
                           class="w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-indigo-500 transition">
                </div>
            </div>

            <!-- Jam Tayang Story -->
            <div>
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Jam Tayang Story Harian (HH:mm)</label>
                <input type="time" name="target_time" value="07:30" required 
                       class="w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 transition">
            </div>

            <!-- Exclude Days -->
            <div id="excludeDaysWrapper">
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Kecualikan Hari (Jangan Posting Pada Hari Ini)</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-3 text-xs">
                    <label class="flex items-center space-x-2 bg-gray-900 p-3 rounded-xl border border-gray-800 cursor-pointer hover:border-gray-700 transition">
                        <input type="checkbox" name="exclude_days[]" value="0" checked class="rounded text-indigo-600 bg-gray-800 border-gray-700">
                        <span>Minggu</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-900 p-3 rounded-xl border border-gray-800 cursor-pointer hover:border-gray-700 transition">
                        <input type="checkbox" name="exclude_days[]" value="1" class="rounded text-indigo-600 bg-gray-800 border-gray-700">
                        <span>Senin</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-900 p-3 rounded-xl border border-gray-800 cursor-pointer hover:border-gray-700 transition">
                        <input type="checkbox" name="exclude_days[]" value="2" class="rounded text-indigo-600 bg-gray-800 border-gray-700">
                        <span>Selasa</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-900 p-3 rounded-xl border border-gray-800 cursor-pointer hover:border-gray-700 transition">
                        <input type="checkbox" name="exclude_days[]" value="3" class="rounded text-indigo-600 bg-gray-800 border-gray-700">
                        <span>Rabu</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-900 p-3 rounded-xl border border-gray-800 cursor-pointer hover:border-gray-700 transition">
                        <input type="checkbox" name="exclude_days[]" value="4" class="rounded text-indigo-600 bg-gray-800 border-gray-700">
                        <span>Kamis</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-900 p-3 rounded-xl border border-gray-800 cursor-pointer hover:border-gray-700 transition">
                        <input type="checkbox" name="exclude_days[]" value="5" class="rounded text-indigo-600 bg-gray-800 border-gray-700">
                        <span>Jumat</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-900 p-3 rounded-xl border border-gray-800 cursor-pointer hover:border-gray-700 transition">
                        <input type="checkbox" name="exclude_days[]" value="6" class="rounded text-indigo-600 bg-gray-800 border-gray-700">
                        <span>Sabtu</span>
                    </label>
                </div>
            </div>

            <!-- Upload Media Pool Area & Preview Grid -->
            <div class="space-y-4">
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider">Unggah Materi Media Pool (Multi-Select File)</label>
                
                <div class="border-2 border-dashed border-gray-700 hover:border-indigo-500 rounded-2xl p-6 text-center bg-gray-900/60 transition space-y-3">
                    <div class="w-12 h-12 bg-indigo-900/40 text-indigo-400 rounded-xl flex items-center justify-center mx-auto text-xl">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    <div>
                        <input type="file" name="media_files[]" multiple required accept="image/*,video/*" id="inputMediaFiles" class="hidden">
                        <label for="inputMediaFiles" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl cursor-pointer shadow-lg transition inline-block">
                            <i class="fa-solid fa-folder-open mr-1.5"></i>Pilih File Gambar / Video
                        </label>
                        <p class="text-xs text-gray-400 mt-2" id="fileCountNotice">Klik untuk memilih beberapa file gambar sekaligus</p>
                    </div>
                </div>

                <!-- Instant Media Preview Grid -->
                <div id="previewContainer" class="hidden space-y-2">
                    <div class="flex items-center justify-between text-xs text-gray-400">
                        <span>Preview Media Terpilih (Klik gambar untuk Lightbox):</span>
                        <span id="previewCountBadge" class="font-bold text-indigo-400"></span>
                    </div>
                    <div id="previewGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3"></div>
                </div>
            </div>

            <!-- Form Action Buttons -->
            <div class="pt-6 border-t border-gray-800 flex items-center justify-end space-x-4">
                <a href="{{ route('projects.index') }}" class="px-5 py-2.5 bg-gray-800 hover:bg-gray-700 text-gray-300 font-semibold rounded-xl text-xs transition">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl text-xs transition shadow-lg flex items-center space-x-2">
                    <i class="fa-solid fa-rocket"></i>
                    <span>Simpan & Inisialisasi Project</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Lightbox Viewer Fullscreen -->
<div id="lightboxModal" class="fixed inset-0 z-50 hidden bg-black/90 backdrop-blur-md flex items-center justify-center p-4">
    <button onclick="closeLightbox()" class="absolute top-5 right-5 text-gray-400 hover:text-white text-3xl z-50 transition">
        <i class="fa-solid fa-xmark"></i>
    </button>

    <button onclick="prevLightbox()" class="absolute left-4 top-1/2 -translate-y-1/2 text-white/70 hover:text-white text-4xl p-2 z-50 transition">
        <i class="fa-solid fa-chevron-left"></i>
    </button>

    <div class="max-w-4xl max-h-[85vh] flex items-center justify-center relative">
        <img id="lightboxImage" src="" class="max-w-full max-h-[85vh] object-contain rounded-xl shadow-2xl hidden">
        <video id="lightboxVideo" controls src="" class="max-w-full max-h-[85vh] rounded-xl shadow-2xl hidden"></video>
    </div>

    <button onclick="nextLightbox()" class="absolute right-4 top-1/2 -translate-y-1/2 text-white/70 hover:text-white text-4xl p-2 z-50 transition">
        <i class="fa-solid fa-chevron-right"></i>
    </button>

    <div id="lightboxCaption" class="absolute bottom-4 left-1/2 -translate-x-1/2 text-xs text-gray-300 bg-gray-900/80 px-4 py-2 rounded-full border border-gray-800 font-mono"></div>
</div>
@endsection

@section('scripts')
<script>
    let lightboxItems = [];
    let currentLightboxIdx = 0;

    function toggleRepeatFields() {
        const repeatType = document.querySelector('input[name="repeat_type"]:checked').value;
        const startWrapper = document.getElementById('startDateWrapper');
        const endWrapper = document.getElementById('endDateWrapper');
        const startLabel = document.getElementById('startDateLabel');
        const excludeDaysWrapper = document.getElementById('excludeDaysWrapper');

        if (repeatType === 'continuous') {
            startWrapper.classList.add('hidden');
            endWrapper.classList.add('hidden');
            excludeDaysWrapper.classList.remove('hidden');
        } else if (repeatType === 'once') {
            startWrapper.classList.remove('hidden');
            endWrapper.classList.add('hidden');
            startLabel.textContent = 'Tanggal Tayang (Single Post)';
            excludeDaysWrapper.classList.add('hidden');
        } else if (repeatType === 'until_date') {
            startWrapper.classList.remove('hidden');
            endWrapper.classList.remove('hidden');
            startLabel.textContent = 'Tanggal Mulai Campaign';
            excludeDaysWrapper.classList.remove('hidden');
        }
    }

    // Filter Portfolios by Account
    function filterPortfoliosByAccount() {
        const accId = document.getElementById('selectMetaAccount').value;
        const portfolioSelect = document.getElementById('selectPortfolio');
        const options = portfolioSelect.querySelectorAll('option');

        let firstVisible = null;
        options.forEach(opt => {
            const optAcc = opt.getAttribute('data-account');
            if (!optAcc || optAcc === accId) {
                opt.style.display = '';
                if (!firstVisible) firstVisible = opt;
            } else {
                opt.style.display = 'none';
            }
        });

        if (firstVisible) {
            portfolioSelect.value = firstVisible.value;
        }
    }

    document.getElementById('selectMetaAccount')?.addEventListener('change', filterPortfoliosByAccount);
    filterPortfoliosByAccount();
    toggleRepeatFields();

    // Instant Media Preview Grid & Lightbox Preparation
    document.getElementById('inputMediaFiles').addEventListener('change', function() {
        const files = Array.from(this.files);
        const container = document.getElementById('previewContainer');
        const grid = document.getElementById('previewGrid');
        const countBadge = document.getElementById('previewCountBadge');
        
        grid.innerHTML = '';
        lightboxItems = [];

        if (files.length === 0) {
            container.classList.add('hidden');
            document.getElementById('fileCountNotice').textContent = 'Belum ada file yang dipilih';
            return;
        }

        container.classList.remove('hidden');
        document.getElementById('fileCountNotice').textContent = `👍 Berhasil memilih ${files.length} file media!`;
        countBadge.textContent = `${files.length} File`;

        files.forEach((file, index) => {
            const fileUrl = URL.createObjectURL(file);
            const isVideo = file.type.startsWith('video/');
            
            lightboxItems.push({
                url: fileUrl,
                name: file.name,
                isVideo: isVideo
            });

            const card = document.createElement('div');
            card.className = 'group relative bg-gray-900 border border-gray-800 hover:border-indigo-500 rounded-xl overflow-hidden shadow cursor-pointer transition';
            card.onclick = () => openLightbox(index);

            let mediaHtml = isVideo 
                ? `<video src="${fileUrl}" class="w-full h-28 object-cover"></video>` 
                : `<img src="${fileUrl}" class="w-full h-28 object-cover group-hover:scale-105 transition duration-300">`;

            card.innerHTML = `
                ${mediaHtml}
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-lg">
                    <i class="fa-solid fa-magnifying-glass-plus"></i>
                </div>
                <div class="p-2 bg-gray-900/90 text-[10px] text-gray-300 truncate font-mono border-t border-gray-800/60">
                    ${file.name}
                </div>
            `;

            grid.appendChild(card);
        });
    });

    // Lightbox Controls
    function openLightbox(index) {
        if (lightboxItems.length === 0) return;
        currentLightboxIdx = index;
        updateLightboxView();
        document.getElementById('lightboxModal').classList.remove('hidden');
    }

    function closeLightbox() {
        document.getElementById('lightboxModal').classList.add('hidden');
        document.getElementById('lightboxVideo').pause();
    }

    function prevLightbox() {
        currentLightboxIdx = (currentLightboxIdx - 1 + lightboxItems.length) % lightboxItems.length;
        updateLightboxView();
    }

    function nextLightbox() {
        currentLightboxIdx = (currentLightboxIdx + 1) % lightboxItems.length;
        updateLightboxView();
    }

    function updateLightboxView() {
        const item = lightboxItems[currentLightboxIdx];
        const imgEl = document.getElementById('lightboxImage');
        const vidEl = document.getElementById('lightboxVideo');
        const capEl = document.getElementById('lightboxCaption');

        if (item.isVideo) {
            imgEl.classList.add('hidden');
            vidEl.classList.remove('hidden');
            vidEl.src = item.url;
            vidEl.play();
        } else {
            vidEl.classList.add('hidden');
            vidEl.pause();
            imgEl.classList.remove('hidden');
            imgEl.src = item.url;
        }

        capEl.textContent = `${currentLightboxIdx + 1} / ${lightboxItems.length} - ${item.name}`;
    }

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        const modal = document.getElementById('lightboxModal');
        if (!modal.classList.contains('hidden')) {
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') prevLightbox();
            if (e.key === 'ArrowRight') nextLightbox();
        }
    });

    // Form Submit Handler
    document.getElementById('formCreateProject').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        showLoading('Menginisialisasi Project Baru...', 'Mengunggah materi media pool dan menginisialisasi buffer penjadwalan...');

        fetch("{{ route('projects.store') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Project Berhasil Dibuat!', data.message);
                setTimeout(() => window.location.href = data.redirect || "{{ route('projects.index') }}", 1500);
            } else {
                showAlert('error', 'Gagal Membuat Project', data.message);
            }
        })
        .catch(err => {
            showAlert('error', 'Kesalahan Sistem', err.message);
        });
    });
</script>
@endsection
