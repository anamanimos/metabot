@extends('layouts.app')

@section('title', 'Daftar Project Campaigns')

@section('content')
<div class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center space-x-2">
                <i class="fa-solid fa-layer-group text-indigo-500"></i>
                <span>Project Campaigns</span>
            </h1>
            <p class="text-sm text-gray-400 mt-1">
                Kelola penjadwalan otomatis berbasis Project dengan **Rolling 30-Day Buffer** dan **Multi-Image Story**.
            </p>
        </div>

        <a href="{{ route('projects.create') }}" 
           class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl shadow-lg transition flex items-center justify-center space-x-2">
            <i class="fa-solid fa-plus"></i>
            <span>Buat Project Baru</span>
        </a>
    </div>

    <!-- Stats Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card-dark p-5 rounded-2xl flex items-center justify-between shadow">
            <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Total Project</p>
                <h3 class="text-2xl font-bold text-white mt-1">{{ $projects->count() }}</h3>
            </div>
            <div class="p-3 bg-indigo-900/40 text-indigo-400 rounded-xl">
                <i class="fa-solid fa-diagram-project text-xl"></i>
            </div>
        </div>

        <div class="card-dark p-5 rounded-2xl flex items-center justify-between shadow">
            <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Project Active</p>
                <h3 class="text-2xl font-bold text-emerald-400 mt-1">{{ $projects->where('status', 'active')->count() }}</h3>
            </div>
            <div class="p-3 bg-emerald-900/40 text-emerald-400 rounded-xl">
                <i class="fa-solid fa-circle-play text-xl"></i>
            </div>
        </div>

        <div class="card-dark p-5 rounded-2xl flex items-center justify-between shadow">
            <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Media Pool Available</p>
                <h3 class="text-2xl font-bold text-blue-400 mt-1">{{ $projects->sum(fn($p) => $p->mediaFiles->count()) }}</h3>
            </div>
            <div class="p-3 bg-blue-900/40 text-blue-400 rounded-xl">
                <i class="fa-solid fa-images text-xl"></i>
            </div>
        </div>

        <div class="card-dark p-5 rounded-2xl flex items-center justify-between shadow">
            <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Rolling Buffer (30 Hari)</p>
                <h3 class="text-2xl font-bold text-purple-400 mt-1">{{ $projects->sum(fn($p) => $p->schedules->count()) }}</h3>
            </div>
            <div class="p-3 bg-purple-900/40 text-purple-400 rounded-xl">
                <i class="fa-solid fa-clock-rotate-left text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Projects Grid -->
    @if($projects->isEmpty())
        <div class="card-dark p-12 rounded-2xl text-center space-y-4">
            <div class="w-16 h-16 bg-gray-800 text-gray-500 rounded-full flex items-center justify-center mx-auto text-2xl">
                <i class="fa-solid fa-folder-open"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-200">Belum ada Project Campaign</h3>
            <p class="text-sm text-gray-400 max-w-md mx-auto">
                Buat project pertama Anda untuk mengaktifkan penjadwalan Story otomatis 30 hari secara kontinu.
            </p>
            <a href="{{ route('projects.create') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg text-sm transition inline-block">
                Buat Project Sekarang
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($projects as $project)
                @php
                    $furthest = $project->schedules->max('target_date');
                    $furthestFormatted = $furthest ? \Carbon\Carbon::parse($furthest)->translatedFormat('d M Y') : 'Belum Dijadwalkan';
                @endphp
                <div class="card-dark rounded-2xl p-6 space-y-5 flex flex-col justify-between shadow-lg relative overflow-hidden border border-gray-800 hover:border-indigo-500/50 transition">
                    
                    <div class="space-y-3">
                        <!-- Card Top: Name & Status Badge -->
                        <div class="flex items-start justify-between">
                            <div>
                                <a href="{{ route('projects.show', $project->id) }}" class="font-bold text-lg text-white leading-snug hover:text-indigo-400 transition flex items-center space-x-1.5">
                                    <span>{{ $project->name }}</span>
                                    <i class="fa-solid fa-arrow-up-right-from-square text-xs text-gray-500"></i>
                                </a>
                                <p class="text-xs text-indigo-400 font-medium mt-0.5">
                                    <i class="fa-solid fa-briefcase mr-1"></i>{{ $project->portfolio_name }}
                                    <span class="text-gray-500">({{ $project->metaAccount?->account_name ?? 'Akun Utama' }})</span>
                                </p>
                            </div>
                            <span class="px-2.5 py-1 text-xs font-bold rounded-full uppercase tracking-wider {{ $project->status === 'active' ? 'bg-emerald-900/60 text-emerald-300 border border-emerald-700' : 'bg-amber-900/60 text-amber-300 border border-amber-700' }}">
                                {{ $project->status }}
                            </span>
                        </div>

                        <!-- Furthest Scheduled Date Badge -->
                        <div class="bg-indigo-950/60 border border-indigo-800/80 p-2.5 rounded-xl flex items-center justify-between text-xs">
                            <span class="text-gray-400">Terjadwal Sampai:</span>
                            <strong class="text-indigo-300 font-bold"><i class="fa-regular fa-calendar-check mr-1"></i>{{ $furthestFormatted }}</strong>
                        </div>

                        <!-- Rule Details -->
                        <div class="grid grid-cols-2 gap-2 text-xs py-2 border-y border-gray-800">
                            <div>
                                <span class="text-gray-500">Jam Tayang:</span>
                                <p class="font-bold text-white mt-0.5"><i class="fa-regular fa-clock text-indigo-400 mr-1"></i>{{ $project->target_time }} WIB</p>
                            </div>
                            <div>
                                <span class="text-gray-500">Multi-Image:</span>
                                <p class="font-bold text-white mt-0.5"><i class="fa-solid fa-images text-indigo-400 mr-1"></i>{{ $project->images_per_post }} Gambar / Story</p>
                            </div>
                        </div>

                        <!-- Excluded Days -->
                        <div class="text-xs space-y-1">
                            <span class="text-gray-500 block">Kecualikan Hari:</span>
                            <div class="flex flex-wrap gap-1">
                                @php
                                    $dayNames = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];
                                    $exDays = $project->exclude_days ?? [];
                                @endphp
                                @if(empty($exDays))
                                    <span class="text-gray-400 italic">Tidak ada (Posting Setiap Hari)</span>
                                @else
                                    @foreach($exDays as $d)
                                        <span class="px-2 py-0.5 bg-red-950/60 text-red-400 border border-red-800 rounded text-[11px]">
                                            {{ $dayNames[$d] ?? $d }}
                                        </span>
                                    @endforeach
                                @endif
                            </div>
                        </div>

                        <!-- Media Pool Counter & Preview -->
                        <div class="text-xs space-y-2 pt-1">
                            <div class="flex items-center justify-between text-gray-400">
                                <span>Galeri Media Pool:</span>
                                <span class="font-bold text-white">{{ $project->mediaFiles->count() }} File Media</span>
                            </div>
                            <div class="flex space-x-1 overflow-x-auto pb-1">
                                @foreach($project->mediaFiles->take(5) as $mf)
                                    <img src="{{ asset($mf->file_path) }}" class="w-10 h-10 object-cover rounded-lg border border-gray-700 shadow-sm" title="{{ $mf->original_name }}">
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Card Actions -->
                    <div class="pt-4 border-t border-gray-800 flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <a href="{{ route('projects.show', $project->id) }}" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold flex items-center space-x-1">
                                <span>Detail</span>
                                <i class="fa-solid fa-chevron-right text-[10px]"></i>
                            </a>
                            <span class="text-gray-700">|</span>
                            <a href="{{ route('projects.edit', $project->id) }}" class="text-xs text-gray-400 hover:text-white font-semibold flex items-center space-x-1">
                                <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                                <span>Edit</span>
                            </a>
                        </div>

                        <button onclick="toggleProjectStatus({{ $project->id }}, '{{ $project->name }}')" 
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5 {{ $project->status === 'active' ? 'bg-amber-600/20 text-amber-300 hover:bg-amber-600/40 border border-amber-700' : 'bg-emerald-600/20 text-emerald-300 hover:bg-emerald-600/40 border border-emerald-700' }}">
                            <i class="fa-solid {{ $project->status === 'active' ? 'fa-pause' : 'fa-play' }}"></i>
                            <span>{{ $project->status === 'active' ? 'Pause' : 'Aktifkan' }}</span>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    function toggleProjectStatus(projectId, projectName) {
        showLoading('Mengubah Status...', `Mengubah status project '${projectName}'...`);

        fetch(`/projects/${projectId}/toggle`, {
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
                showAlert('success', 'Status Diperbarui', data.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showAlert('error', 'Gagal Mengubah Status', data.message);
            }
        })
        .catch(err => {
            showAlert('error', 'Kesalahan Sistem', err.message);
        });
    }
</script>
@endsection
