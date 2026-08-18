@extends('layouts.app')

@section('title', 'Edit Project - ' . $project->name)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Breadcrumb -->
    <div class="flex items-center space-x-2 text-xs text-gray-400">
        <a href="{{ route('projects.index') }}" class="hover:text-white transition flex items-center space-x-1">
            <i class="fa-solid fa-folder-open"></i>
            <span>Project Campaigns</span>
        </a>
        <span>/</span>
        <a href="{{ route('projects.show', $project->id) }}" class="hover:text-white transition">
            {{ $project->name }}
        </a>
        <span>/</span>
        <span class="text-white font-semibold">Edit Project</span>
    </div>

    <!-- Main Card Form -->
    <div class="card-dark rounded-2xl p-6 sm:p-8 shadow-2xl border border-gray-800 space-y-6">
        <div class="border-b border-gray-800 pb-4 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center space-x-3">
                    <div class="p-2.5 bg-gradient-to-tr from-amber-600 to-indigo-600 rounded-xl text-white text-lg">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </div>
                    <span>Edit Project: {{ $project->name }}</span>
                </h1>
                <p class="text-sm text-gray-400 mt-1">
                    Perbarui aturan jam tayang, aset target, dan stok media pool untuk campaign ini.
                </p>
            </div>
        </div>

        <form id="formEditProject" enctype="multipart/form-data" class="space-y-6">
            @method('PUT')

            <!-- Nama Project -->
            <div>
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Nama Project / Campaign</label>
                <input type="text" name="name" value="{{ $project->name }}" required 
                       class="w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 transition">
            </div>

            <!-- Akun & Portofolio Target -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Akun Meta</label>
                    <input type="text" disabled value="{{ $project->metaAccount?->account_name ?? 'Akun Utama' }}" 
                           class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-3 text-sm text-gray-400 cursor-not-allowed">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Aset Bisnis Target</label>
                    <select name="portfolio_name" required class="w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 transition">
                        @foreach($portfolios as $p)
                            <option value="{{ $p->name }}" {{ $project->portfolio_name === $p->name ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Jam Tayang & Jumlah Gambar per Post -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Jam Tayang Story Harian (HH:mm)</label>
                    <input type="time" name="target_time" value="{{ $project->target_time }}" required 
                           class="w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-indigo-500 transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Jumlah Gambar / Post (Multi-Image)</label>
                    <select name="images_per_post" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 transition">
                        <option value="1" {{ $project->images_per_post == 1 ? 'selected' : '' }}>1 Gambar (Single Story)</option>
                        <option value="2" {{ $project->images_per_post == 2 ? 'selected' : '' }}>2 Gambar (Multi-Image Story)</option>
                        <option value="3" {{ $project->images_per_post == 3 ? 'selected' : '' }}>3 Gambar</option>
                        <option value="4" {{ $project->images_per_post == 4 ? 'selected' : '' }}>4 Gambar</option>
                    </select>
                </div>
            </div>

            <!-- Exclude Days -->
            <div>
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Kecualikan Hari (Jangan Posting Pada Hari Ini)</label>
                @php $exDays = $project->exclude_days ?? []; @endphp
                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-3 text-xs">
                    <label class="flex items-center space-x-2 bg-gray-900 p-3 rounded-xl border border-gray-800 cursor-pointer">
                        <input type="checkbox" name="exclude_days[]" value="0" {{ in_array(0, $exDays) ? 'checked' : '' }} class="rounded text-indigo-600 bg-gray-800 border-gray-700">
                        <span>Minggu</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-900 p-3 rounded-xl border border-gray-800 cursor-pointer">
                        <input type="checkbox" name="exclude_days[]" value="1" {{ in_array(1, $exDays) ? 'checked' : '' }} class="rounded text-indigo-600 bg-gray-800 border-gray-700">
                        <span>Senin</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-900 p-3 rounded-xl border border-gray-800 cursor-pointer">
                        <input type="checkbox" name="exclude_days[]" value="2" {{ in_array(2, $exDays) ? 'checked' : '' }} class="rounded text-indigo-600 bg-gray-800 border-gray-700">
                        <span>Selasa</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-900 p-3 rounded-xl border border-gray-800 cursor-pointer">
                        <input type="checkbox" name="exclude_days[]" value="3" {{ in_array(3, $exDays) ? 'checked' : '' }} class="rounded text-indigo-600 bg-gray-800 border-gray-700">
                        <span>Rabu</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-900 p-3 rounded-xl border border-gray-800 cursor-pointer">
                        <input type="checkbox" name="exclude_days[]" value="4" {{ in_array(4, $exDays) ? 'checked' : '' }} class="rounded text-indigo-600 bg-gray-800 border-gray-700">
                        <span>Kamis</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-900 p-3 rounded-xl border border-gray-800 cursor-pointer">
                        <input type="checkbox" name="exclude_days[]" value="5" {{ in_array(5, $exDays) ? 'checked' : '' }} class="rounded text-indigo-600 bg-gray-800 border-gray-700">
                        <span>Jumat</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-900 p-3 rounded-xl border border-gray-800 cursor-pointer">
                        <input type="checkbox" name="exclude_days[]" value="6" {{ in_array(6, $exDays) ? 'checked' : '' }} class="rounded text-indigo-600 bg-gray-800 border-gray-700">
                        <span>Sabtu</span>
                    </label>
                </div>
            </div>

            <!-- Existing Media Gallery Preview (With Lightbox) -->
            <div class="space-y-3">
                <div class="flex items-center justify-between text-xs text-gray-300">
                    <span class="font-semibold uppercase tracking-wider text-xs">Galeri Media Pool Aktif Saat Ini (Klik untuk Lightbox)</span>
                    <span class="text-indigo-400 font-bold">{{ $project->mediaFiles->count() }} File Media</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    @foreach($project->mediaFiles as $index => $media)
                        <div class="group relative bg-gray-900 border border-gray-800 hover:border-indigo-500 rounded-xl overflow-hidden shadow cursor-pointer transition"
                             onclick="openExistingLightbox({{ $index }})">
                            <img src="{{ asset($media->file_path) }}" class="w-full h-24 object-cover group-hover:scale-105 transition duration-300">
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-lg">
                                <i class="fa-solid fa-magnifying-glass-plus"></i>
                            </div>
                            <div class="p-1.5 bg-gray-900/90 text-[10px] text-gray-300 truncate font-mono border-t border-gray-800">
                                {{ $media->original_name }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Tambah Media Baru & Preview Grid -->
            <div class="space-y-4 pt-2 border-t border-gray-800">
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider">Tambah File Media Baru ke Pool (Opsional)</label>
                
                <div class="border-2 border-dashed border-gray-700 hover:border-indigo-500 rounded-2xl p-6 text-center bg-gray-900/60 transition space-y-3">
                    <div class="w-12 h-12 bg-indigo-900/40 text-indigo-400 rounded-xl flex items-center justify-center mx-auto text-xl">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    <div>
                        <input type="file" name="media_files[]" multiple accept="image/*,video/*" id="inputMediaFiles" class="hidden">
                        <label for="inputMediaFiles" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl cursor-pointer shadow-lg transition inline-block">
                            <i class="fa-solid fa-folder-open mr-1.5"></i>Pilih File Media Tambahan
                        </label>
                        <p class="text-xs text-gray-400 mt-2" id="fileCountNotice">Pilih file baru jika ingin menambahkan materi media pool baru</p>
                    </div>
                </div>

                <!-- Instant Media Preview Grid for New Uploads -->
                <div id="newPreviewContainer" class="hidden space-y-2">
                    <div class="flex items-center justify-between text-xs text-gray-400">
                        <span>Preview Media Baru Akan Diunggah:</span>
                        <span id="newPreviewBadge" class="font-bold text-indigo-400"></span>
                    </div>
                    <div id="newPreviewGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3"></div>
                </div>
            </div>

            <!-- Form Action Buttons -->
            <div class="pt-6 border-t border-gray-800 flex items-center justify-end space-x-4">
                <a href="{{ route('projects.show', $project->id) }}" class="px-5 py-2.5 bg-gray-800 hover:bg-gray-700 text-gray-300 font-semibold rounded-xl text-xs transition">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl text-xs transition shadow-lg flex items-center space-x-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Simpan Perubahan Project</span>
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
    // Initial Lightbox Items from Existing Media Pool
    let lightboxItems = [
        @foreach($project->mediaFiles as $media)
            {
                url: "{{ asset($media->file_path) }}",
                name: "{{ $media->original_name }}",
                isVideo: {{ Str::contains($media->mime_type ?? '', 'video') ? 'true' : 'false' }}
            },
        @endforeach
    ];
    let currentLightboxIdx = 0;

    function openExistingLightbox(index) {
        currentLightboxIdx = index;
        updateLightboxView();
        document.getElementById('lightboxModal').classList.remove('hidden');
    }

    // New Media Files Instant Preview & Lightbox
    document.getElementById('inputMediaFiles').addEventListener('change', function() {
        const files = Array.from(this.files);
        const container = document.getElementById('newPreviewContainer');
        const grid = document.getElementById('newPreviewGrid');
        const badge = document.getElementById('newPreviewBadge');

        grid.innerHTML = '';

        if (files.length === 0) {
            container.classList.add('hidden');
            document.getElementById('fileCountNotice').textContent = 'Pilih file baru jika ingin menambahkan materi media pool baru';
            return;
        }

        container.classList.remove('hidden');
        document.getElementById('fileCountNotice').textContent = `👍 Berhasil memilih ${files.length} file media tambahan!`;
        badge.textContent = `${files.length} File Baru`;

        const existingCount = {{ $project->mediaFiles->count() }};

        files.forEach((file, index) => {
            const fileUrl = URL.createObjectURL(file);
            const isVideo = file.type.startsWith('video/');
            
            const newIndex = lightboxItems.length;
            lightboxItems.push({
                url: fileUrl,
                name: file.name,
                isVideo: isVideo
            });

            const card = document.createElement('div');
            card.className = 'group relative bg-gray-900 border border-gray-800 hover:border-indigo-500 rounded-xl overflow-hidden shadow cursor-pointer transition';
            card.onclick = () => openExistingLightbox(newIndex);

            let mediaHtml = isVideo 
                ? `<video src="${fileUrl}" class="w-full h-24 object-cover"></video>` 
                : `<img src="${fileUrl}" class="w-full h-24 object-cover group-hover:scale-105 transition duration-300">`;

            card.innerHTML = `
                ${mediaHtml}
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-lg">
                    <i class="fa-solid fa-magnifying-glass-plus"></i>
                </div>
                <div class="p-1.5 bg-gray-900/90 text-[10px] text-gray-300 truncate font-mono border-t border-gray-800">
                    ${file.name}
                </div>
            `;

            grid.appendChild(card);
        });
    });

    // Lightbox Controls
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
    document.getElementById('formEditProject').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        showLoading('Memperbarui Project...', 'Menyimpan perubahan konfigurasi project...');

        fetch("{{ route('projects.update', $project->id) }}", {
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
                showAlert('success', 'Project diperbarui!', data.message);
                setTimeout(() => window.location.href = data.redirect || "{{ route('projects.show', $project->id) }}", 1200);
            } else {
                showAlert('error', 'Gagal Memperbarui', data.message);
            }
        })
        .catch(err => {
            showAlert('error', 'Kesalahan Sistem', err.message);
        });
    });
</script>
@endsection
