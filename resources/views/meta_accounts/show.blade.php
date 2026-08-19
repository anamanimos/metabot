@extends('layouts.app')

@section('title', 'Detail Akun Meta - ' . $account->account_name)

@section('content')
<div class="space-y-6">

    <!-- Breadcrumb -->
    <div class="flex items-center space-x-2 text-xs text-gray-400">
        <a href="{{ route('meta-accounts.index') }}" class="hover:text-white transition flex items-center space-x-1">
            <i class="fa-solid fa-users"></i>
            <span>Akun Meta</span>
        </a>
        <span>/</span>
        <span class="text-white font-semibold">{{ $account->account_name }}</span>
    </div>

    <!-- Header & Account Info Card -->
    <div class="card-dark rounded-2xl p-6 shadow-xl border border-gray-800 space-y-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div class="space-y-2">
                <div class="flex items-center space-x-3">
                    <div class="p-3 bg-gradient-to-tr from-indigo-600 to-purple-600 rounded-xl text-white font-bold text-xl">
                        <i class="fa-brands fa-facebook-f"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-white">{{ $account->account_name }}</h1>
                        <p class="text-xs text-gray-400 mt-0.5 font-mono">
                            <i class="fa-regular fa-folder text-indigo-400 mr-1"></i>Folder Sesi: <strong>{{ $account->session_folder }}</strong>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Action Button Ambil Portofolio -->
            <div class="flex items-center space-x-3">
                <button onclick="fetchAccountPortfolios()" 
                        class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-xl shadow-lg transition flex items-center space-x-2">
                    <i class="fa-solid fa-rotate text-sm"></i>
                    <span>Ambil Portofolio Akun Ini</span>
                </button>
            </div>
        </div>

        <!-- Account Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-4 border-t border-gray-800 text-xs">
            <div class="bg-gray-900/60 p-3 rounded-xl border border-gray-800">
                <span class="text-gray-500 block">Status Sesi:</span>
                <p class="font-bold text-sm mt-0.5 uppercase {{ $account->status === 'active' ? 'text-emerald-400' : 'text-amber-400' }}">
                    <i class="fa-solid fa-circle text-[10px] mr-1.5"></i>{{ $account->status }}
                </p>
            </div>

            <div class="bg-gray-900/60 p-3 rounded-xl border border-gray-800">
                <span class="text-gray-500 block">Total Aset Bisnis / Portofolio:</span>
                <p class="font-bold text-white text-sm mt-0.5">
                    <i class="fa-solid fa-briefcase text-indigo-400 mr-1.5"></i>{{ $account->portfolios->count() }} Aset
                </p>
            </div>

            <div class="bg-gray-900/60 p-3 rounded-xl border border-gray-800">
                <span class="text-gray-500 block">Project Terhubung:</span>
                <p class="font-bold text-white text-sm mt-0.5">
                    <i class="fa-solid fa-layer-group text-indigo-400 mr-1.5"></i>{{ $account->projects->count() }} Campaign
                </p>
            </div>
        </div>
    </div>

    <!-- Section Aset Bisnis / Portofolio Terikat (Struktur 2-Tingkat) -->
    <div class="card-dark rounded-2xl p-6 space-y-4 shadow-xl border border-gray-800">
        <div class="flex items-center justify-between border-b border-gray-800 pb-3">
            <h2 class="text-lg font-bold text-white flex items-center space-x-2">
                <i class="fa-solid fa-briefcase text-indigo-400"></i>
                <span>Aset Bisnis 2-Tingkat (Halaman FB & Profil IG Per Grup)</span>
            </h2>
            <span class="text-xs text-gray-400">Total: <strong>{{ $account->portfolios->count() }} Aset Spesifik</strong></span>
        </div>

        @if($account->portfolios->isEmpty())
            <div class="p-8 text-center text-gray-500 space-y-2">
                <p>Belum ada Aset Bisnis yang terdaftar untuk akun ini.</p>
                <p class="text-xs text-gray-400">Klik tombol <strong>"Ambil Portofolio Akun Ini"</strong> di atas untuk memindai aset bisnis dari Meta.</p>
            </div>
        @else
            @php
                $groupedPortfolios = $account->portfolios->groupBy(function($item) {
                    return $item->portfolio_name ?? $item->name;
                });
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach($groupedPortfolios as $groupName => $assets)
                    <div class="bg-gray-900/80 border border-gray-800 p-4 rounded-xl space-y-3 hover:border-indigo-500/50 transition shadow">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <div class="p-2 bg-indigo-900/40 text-indigo-400 rounded-lg text-sm">
                                    <i class="fa-solid fa-building"></i>
                                </div>
                                <h4 class="font-bold text-white text-sm truncate">{{ $groupName }}</h4>
                            </div>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-indigo-950 text-indigo-300 border border-indigo-800">
                                {{ $assets->count() }} Aset
                            </span>
                        </div>

                        <div class="space-y-1.5 pt-2 border-t border-gray-800 text-xs">
                            @foreach($assets as $assetItem)
                                <div class="p-2 bg-gray-950/60 rounded-lg border border-gray-800/80 space-y-0.5">
                                    <div class="font-semibold text-gray-200 truncate">
                                        {{ $assetItem->asset_name ?? $assetItem->name }}
                                    </div>
                                    @if($assetItem->asset_type)
                                        <div class="text-[10px] text-indigo-400/80 truncate">
                                            <i class="fa-solid fa-layer-group text-[9px] mr-1"></i>{{ $assetItem->asset_type }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Section Project Campaigns Terkait -->
    <div class="card-dark rounded-2xl overflow-hidden shadow-xl border border-gray-800 space-y-4">
        <div class="p-6 pb-0 flex items-center justify-between">
            <h2 class="text-lg font-bold text-white flex items-center space-x-2">
                <i class="fa-solid fa-layer-group text-indigo-400"></i>
                <span>Project Campaigns Menggunakan Akun Ini</span>
            </h2>
            <span class="text-xs text-gray-400">Total: <strong>{{ $account->projects->count() }} Project</strong></span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-300">
                <thead class="bg-gray-900/80 text-xs uppercase tracking-wider text-gray-400 border-b border-gray-800">
                    <tr>
                        <th class="px-6 py-4">Nama Project</th>
                        <th class="px-6 py-4">Aset Target</th>
                        <th class="px-6 py-4">Jam Tayang</th>
                        <th class="px-6 py-4">Mode Posting</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($account->projects as $proj)
                        <tr class="hover:bg-gray-800/40 transition">
                            <td class="px-6 py-4 font-bold text-white">{{ $proj->name }}</td>
                            <td class="px-6 py-4 font-semibold text-indigo-300">{{ $proj->portfolio_name }}</td>
                            <td class="px-6 py-4 font-semibold text-gray-300"><i class="fa-regular fa-clock text-indigo-400 mr-1"></i>{{ $proj->target_time }} WIB</td>
                            <td class="px-6 py-4 text-xs text-gray-400">{{ $proj->images_per_post }} Gambar / Story</td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 text-xs font-bold rounded-full uppercase tracking-wider {{ $proj->status === 'active' ? 'bg-emerald-900/60 text-emerald-300 border border-emerald-700' : 'bg-amber-900/60 text-amber-700 border border-amber-700' }}">
                                    {{ $proj->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('projects.show', $proj->id) }}" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold">
                                    Detail <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">Belum ada project campaign yang terhubung dengan akun ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function fetchAccountPortfolios() {
        showLoading('Memindai Meta...', 'Membuka bot Chromium untuk mengambil Aset Bisnis terikat akun ini...');

        fetch("{{ route('portfolios.fetch') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                meta_account_id: {{ $account->id }}
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Berhasil Dipindai!', data.message);
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showAlert('error', 'Gagal Memindai', data.message);
            }
        })
        .catch(err => {
            showAlert('error', 'Kesalahan Sistem', err.message);
        });
    }
</script>
@endsection
