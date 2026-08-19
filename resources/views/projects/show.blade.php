@extends('layouts.app')

@section('title', 'Detail Project - ' . $project->name)

@section('content')
<div class="space-y-6">

    <!-- Breadcrumb & Header Navigation -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-gray-400 mb-1">
                <a href="{{ route('projects.index') }}" class="hover:text-white transition flex items-center space-x-1">
                    <i class="fa-solid fa-folder-open"></i>
                    <span>Project Campaigns</span>
                </a>
                <span>/</span>
                <span class="text-white font-semibold">{{ $project->name }}</span>
            </div>
            <h1 class="text-2xl font-bold text-white flex items-center space-x-3">
                <span>{{ $project->name }}</span>
                <span class="px-2.5 py-0.5 text-xs font-extrabold rounded-full {{ $project->status === 'active' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-amber-950 text-amber-400 border border-amber-800' }}">
                    {{ strtoupper($project->status) }}
                </span>
                <span class="px-2.5 py-0.5 text-xs font-extrabold rounded-full bg-indigo-950 text-indigo-300 border border-indigo-800">
                    {{ strtoupper($project->repeat_type) }}
                </span>
            </h1>
        </div>

        <div class="flex items-center space-x-3">
            <a href="{{ route('projects.edit', $project->id) }}" 
               class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs font-semibold rounded-xl border border-gray-700 transition flex items-center space-x-1.5 shadow">
                <i class="fa-solid fa-pen-to-square"></i>
                <span>Edit Project</span>
            </a>

            <button onclick="toggleProjectStatus({{ $project->id }})" 
                    class="px-4 py-2 text-xs font-semibold rounded-xl transition flex items-center space-x-1.5 shadow {{ $project->status === 'active' ? 'bg-amber-900/40 text-amber-300 border border-amber-800 hover:bg-amber-800/60' : 'bg-emerald-900/40 text-emerald-300 border border-emerald-800 hover:bg-emerald-800/60' }}">
                <i class="fa-solid {{ $project->status === 'active' ? 'fa-pause' : 'fa-play' }}"></i>
                <span>{{ $project->status === 'active' ? 'Pause Campaign' : 'Aktifkan Campaign' }}</span>
            </button>
        </div>
    </div>

    <!-- Stats & Schedule Status Banner -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Badge Terjadwal Sampai Kapan -->
        <div class="card-dark p-6 rounded-2xl border border-indigo-900/50 bg-gradient-to-br from-gray-900 to-indigo-950/40 flex items-center space-x-4 shadow-lg">
            <div class="p-4 bg-indigo-600 text-white rounded-2xl shadow-lg">
                <i class="fa-solid fa-calendar-check text-2xl"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-indigo-300 uppercase tracking-wider">Terjadwal Sampai Tanggal:</p>
                <h3 class="text-xl font-extrabold text-white mt-0.5">{{ $furthestDateFormatted }}</h3>
                <p class="text-[11px] text-indigo-400/80 mt-1 font-medium">
                    {{ $pendingCount }} Hari Antrean PENDING Aktif
                </p>
            </div>
        </div>

        <div class="card-dark p-5 rounded-2xl flex items-center justify-between shadow">
            <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Target Aset Meta</p>
                <h3 class="text-lg font-bold text-white mt-1">{{ $project->portfolio_name }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ $project->metaAccount?->account_name ?? 'Akun Utama' }}</p>
            </div>
            <div class="p-3 bg-purple-900/40 text-purple-400 rounded-xl">
                <i class="fa-solid fa-briefcase text-xl"></i>
            </div>
        </div>

        <div class="card-dark p-5 rounded-2xl flex items-center justify-between shadow">
            <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Jam Tayang Harian</p>
                <h3 class="text-lg font-bold text-indigo-300 mt-1"><i class="fa-regular fa-clock mr-1"></i>{{ $project->target_time }} WIB</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ $completedCount }} Story Telah Tayang</p>
            </div>
            <div class="p-3 bg-blue-900/40 text-blue-400 rounded-xl">
                <i class="fa-solid fa-regular fa-clock text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Media Pool Gallery Section -->
    <div class="card-dark rounded-2xl p-6 shadow-xl border border-gray-800 space-y-4">
        <div class="flex items-center justify-between border-b border-gray-800 pb-3">
            <h2 class="text-lg font-bold text-white flex items-center space-x-2">
                <i class="fa-solid fa-photo-film text-indigo-400"></i>
                <span>Galeri Media Pool Project ({{ $project->mediaFiles->count() }} File)</span>
            </h2>
            <button onclick="openAddMediaModal()" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg text-xs transition shadow flex items-center space-x-1.5">
                <i class="fa-solid fa-plus"></i>
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
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($project->schedules as $item)
                        <tr class="hover:bg-gray-800/40 transition">
                            <td class="px-6 py-4 font-mono font-semibold text-white text-xs">{{ $item->item_code }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $urls = $item->media_urls;
                                @endphp
                                <div class="flex items-center space-x-1">
                                    @foreach($urls as $u)
                                        <img src="{{ $u }}" class="w-9 h-9 object-cover rounded-lg border border-gray-700 shadow-sm">
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
                            <td class="px-6 py-4 text-right">
                                <button onclick="runSingleSchedule({{ $item->id }}, '{{ $item->item_code }}')" 
                                        class="px-3 py-1.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-bold rounded-xl shadow transition flex items-center space-x-1.5"
                                        title="Kirim item jadwal ini saja secara satuan">
                                    <i class="fa-solid fa-paper-plane text-[11px]"></i>
                                    <span>{{ $item->status === 'failed' ? 'Kirim Ulang' : 'Kirim Satuan' }}</span>
                                </button>
                            </td>
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
                <i class="fa-solid fa-plus-circle text-indigo-400"></i>
                <span>Tambah Media Pool Baru</span>
            </h3>
            <button onclick="closeAddMediaModal()" class="text-gray-400 hover:text-white transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form id="formAddMedia" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Pilih File Gambar / Video Tambahan</label>
                <input type="file" name="media_files[]" multiple required accept="image/*,video/*" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500">
            </div>

            <div class="pt-3 border-t border-gray-800 flex justify-end space-x-3">
                <button type="button" onclick="closeAddMediaModal()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 font-semibold rounded-xl text-xs transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl text-xs transition shadow flex items-center space-x-1.5">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Unggah Media</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function runSingleSchedule(id, itemCode) {
        showLoading('Memproses Pengiriman Satuan...', `Memicu eksekusi bot Playwright khusus untuk item '${itemCode}'...`);

        fetch(`/schedules/${id}/run-single`, {
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
                showAlert('success', 'Berhasil Diprosed!', data.message);
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showAlert('error', 'Gagal Eksekusi', data.message);
            }
        })
        .catch(err => {
            showAlert('error', 'Kesalahan Sistem', err.message);
        });
    }

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
                showAlert('success', 'Berhasil!', data.message);
                setTimeout(() => window.location.reload(), 1200);
            } else {
                showAlert('error', 'Gagal', data.message);
            }
        })
        .catch(err => {
            showAlert('error', 'Kesalahan Sistem', err.message);
        });
    });

    function toggleProjectStatus(id) {
        fetch(`/projects/${id}/toggle-status`, {
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
                showAlert('success', 'Status Diubah!', data.message);
                setTimeout(() => window.location.reload(), 1200);
            } else {
                showAlert('error', 'Gagal', data.message);
            }
        })
        .catch(err => {
            showAlert('error', 'Kesalahan Sistem', err.message);
        });
    }
</script>
@endsection
