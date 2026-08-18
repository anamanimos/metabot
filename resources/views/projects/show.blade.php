@extends('layouts.app')

@section('title', 'Detail Project - ' . $project->name)

@section('content')
<div class="space-y-6">

    <!-- Breadcrumb & Navigation -->
    <div class="flex items-center justify-between text-xs text-gray-400">
        <div class="flex items-center space-x-2">
            <a href="{{ route('projects.index') }}" class="hover:text-white transition flex items-center space-x-1">
                <i class="fa-solid fa-folder-open"></i>
                <span>Project Campaigns</span>
            </a>
            <span>/</span>
            <span class="text-white font-semibold">{{ $project->name }}</span>
        </div>

        <a href="{{ route('projects.edit', $project->id) }}" 
           class="px-3.5 py-1.5 bg-gray-800 hover:bg-gray-700 text-gray-200 hover:text-white font-semibold rounded-xl border border-gray-700 transition flex items-center space-x-1.5">
            <i class="fa-solid fa-pen-to-square text-indigo-400"></i>
            <span>Edit Project</span>
        </a>
    </div>

    <!-- Highlight Header Card -->
    <div class="card-dark rounded-2xl p-6 shadow-xl border border-gray-800 space-y-6 relative overflow-hidden">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div class="space-y-2">
                <div class="flex items-center space-x-3">
                    <h1 class="text-2xl font-bold text-white">{{ $project->name }}</h1>
                    <span class="px-3 py-1 text-xs font-bold rounded-full uppercase tracking-wider {{ $project->status === 'active' ? 'bg-emerald-900/60 text-emerald-300 border border-emerald-700' : 'bg-amber-900/60 text-amber-300 border border-amber-700' }}">
                        {{ $project->status }}
                    </span>
                </div>
                <p class="text-sm text-gray-400">
                    Target Aset: <strong class="text-indigo-300 font-semibold">{{ $project->portfolio_name }}</strong> &bull; 
                    Akun: <strong class="text-gray-200 font-semibold">{{ $project->metaAccount?->account_name ?? 'Akun Utama' }}</strong>
                </p>
            </div>

            <!-- Furthest Schedule Date Badge -->
            <div class="bg-gradient-to-br from-indigo-900/80 to-purple-900/80 border border-indigo-700/60 p-4 rounded-2xl flex items-center space-x-4 shadow-lg">
                <div class="p-3 bg-indigo-600 text-white rounded-xl text-2xl shadow">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-indigo-300">Terjadwal Sampai Tanggal:</span>
                    <h3 class="text-xl font-extrabold text-white mt-0.5">{{ $furthestDateFormatted }}</h3>
                    <p class="text-xs text-indigo-200 mt-0.5">{{ $pendingCount }} Hari Antrean PENDING Aktif</p>
                </div>
            </div>
        </div>

        <!-- Project Rules Specs -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-gray-800 text-xs">
            <div class="bg-gray-900/60 p-3 rounded-xl border border-gray-800">
                <span class="text-gray-500 block">Jam Tayang:</span>
                <p class="font-bold text-white text-sm mt-0.5"><i class="fa-regular fa-clock text-indigo-400 mr-1.5"></i>{{ $project->target_time }} WIB</p>
            </div>

            <div class="bg-gray-900/60 p-3 rounded-xl border border-gray-800">
                <span class="text-gray-500 block">Mode Posting:</span>
                <p class="font-bold text-white text-sm mt-0.5"><i class="fa-solid fa-images text-indigo-400 mr-1.5"></i>{{ $project->images_per_post }} Gambar / Story</p>
            </div>

            <div class="bg-gray-900/60 p-3 rounded-xl border border-gray-800">
                <span class="text-gray-500 block">Kecualikan Hari:</span>
                <p class="font-bold text-white text-sm mt-0.5">
                    @php
                        $dayNames = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];
                        $exDays = $project->exclude_days ?? [];
                    @endphp
                    @if(empty($exDays))
                        Tidak ada
                    @else
                        {{ implode(', ', array_map(fn($d) => $dayNames[$d] ?? $d, $exDays)) }}
                    @endif
                </p>
            </div>

            <div class="bg-gray-900/60 p-3 rounded-xl border border-gray-800">
                <span class="text-gray-500 block">Total Pool Media:</span>
                <p class="font-bold text-white text-sm mt-0.5"><i class="fa-solid fa-photo-film text-indigo-400 mr-1.5"></i>{{ $project->mediaFiles->count() }} File</p>
            </div>
        </div>
    </div>

    <!-- Media Pool Gallery Section -->
    <div class="card-dark rounded-2xl p-6 space-y-4 shadow-xl border border-gray-800">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-white flex items-center space-x-2">
                <i class="fa-solid fa-images text-indigo-400"></i>
                <span>Galeri Media Pool Project</span>
            </h2>

            <button onclick="openAddMediaModal()" 
                    class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-200 hover:text-white font-semibold text-xs rounded-xl border border-gray-700 transition flex items-center space-x-1.5">
                <i class="fa-solid fa-plus text-indigo-400"></i>
                <span>Tambah Media Baru</span>
            </button>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
            @foreach($project->mediaFiles as $media)
                <div class="group relative bg-gray-900 border border-gray-800 rounded-xl overflow-hidden shadow">
                    <img src="{{ asset($media->file_path) }}" class="w-full h-32 object-cover group-hover:scale-105 transition duration-300">
                    <div class="p-2 bg-gray-900/90 text-[10px] text-gray-400 truncate">
                        {{ $media->original_name }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Schedule Queue Table for This Project -->
    <div class="card-dark rounded-2xl overflow-hidden shadow-xl border border-gray-800 space-y-4">
        <div class="p-6 pb-0 flex items-center justify-between">
            <h2 class="text-lg font-bold text-white flex items-center space-x-2">
                <i class="fa-solid fa-clock-rotate-left text-indigo-400"></i>
                <span>Antrean Penjadwalan Project Ini</span>
            </h2>
            <span class="text-xs text-gray-400">Total: <strong>{{ $project->schedules->count() }} Slot Tayang</strong></span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-300">
                <thead class="bg-gray-900/80 text-xs uppercase tracking-wider text-gray-400 border-b border-gray-800">
                    <tr>
                        <th class="px-6 py-4">Item Code</th>
                        <th class="px-6 py-4">Media Preview</th>
                        <th class="px-6 py-4">Tanggal Tayang</th>
                        <th class="px-6 py-4">Jam Tayang</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($project->schedules as $item)
                        <tr class="hover:bg-gray-800/40 transition">
                            <td class="px-6 py-4 font-mono font-semibold text-white text-xs">{{ $item->item_code }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $paths = $item->media_paths ?? [$item->media_path];
                                @endphp
                                <div class="flex items-center space-x-1">
                                    @foreach($paths as $p)
                                        <img src="{{ asset($p) }}" class="w-9 h-9 object-cover rounded-lg border border-gray-700 shadow-sm">
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-4 font-semibold text-white">{{ $item->target_date->translatedFormat('l, d F Y') }}</td>
                            <td class="px-6 py-4 font-semibold text-indigo-300"><i class="fa-regular fa-clock mr-1"></i>{{ $item->target_time }} WIB</td>
                            <td class="px-6 py-4">
                                @if($item->status === 'pending')
                                    <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-amber-950 text-amber-300 border border-amber-800">PENDING</span>
                                @elseif($item->status === 'completed')
                                    <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-emerald-950 text-emerald-300 border border-emerald-800">COMPLETED</span>
                                @else
                                    <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-red-950 text-red-300 border border-red-800">FAILED</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-400">{{ $item->notes }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">Belum ada antrean jadwal untuk project ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Media Pool -->
<div id="modalAddMedia" class="fixed inset-0 z-50 hidden bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="card-dark w-full max-w-md rounded-2xl shadow-2xl border border-gray-800 p-6 space-y-5 relative">
        <div class="flex items-center justify-between border-b border-gray-800 pb-3">
            <h3 class="text-lg font-bold text-white flex items-center space-x-2">
                <i class="fa-solid fa-photo-film text-indigo-400"></i>
                <span>Tambah Media Baru ke Project</span>
            </h3>
            <button onclick="closeAddMediaModal()" class="text-gray-400 hover:text-white transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form id="formAddMedia" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1">Pilih File Gambar/Video</label>
                <input type="file" name="media_files[]" multiple required accept="image/*,video/*" 
                       class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-sm text-gray-300 focus:outline-none">
            </div>

            <div class="pt-3 border-t border-gray-800 flex justify-end space-x-3">
                <button type="button" onclick="closeAddMediaModal()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 font-semibold rounded-xl text-xs transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl text-xs transition shadow-lg flex items-center space-x-2">
                    <i class="fa-solid fa-upload"></i>
                    <span>Unggah Media</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openAddMediaModal() {
        document.getElementById('modalAddMedia').classList.remove('hidden');
    }

    function closeAddMediaModal() {
        document.getElementById('modalAddMedia').classList.add('hidden');
    }

    document.getElementById('formAddMedia')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        showLoading('Mengunggah Media...', 'Menambahkan file media baru ke pool project...');

        fetch("{{ route('projects.addMedia', $project->id) }}", {
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
                showAlert('success', 'Berhasil Ditambahkan!', data.message);
                closeAddMediaModal();
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showAlert('error', 'Gagal Mengunggah', data.message);
            }
        })
        .catch(err => {
            showAlert('error', 'Kesalahan Sistem', err.message);
        });
    });
</script>
@endsection
