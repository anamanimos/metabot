@extends('layouts.app')

@section('title', 'Daftar Antrean Story')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center space-x-2">
                <i class="fa-solid fa-calendar-days text-indigo-500"></i>
                <span>Antrean Penjadwalan Story</span>
            </h1>
            <p class="text-sm text-gray-400 mt-1">
                Daftar antrean rolling buffer 30 hari yang akan dieksekusi otomatis oleh Playwright Bot ke Meta Business Suite.
            </p>
        </div>

        <!-- Filter Status -->
        <div class="flex items-center space-x-2 bg-gray-900 p-1.5 rounded-xl border border-gray-800 text-xs">
            <a href="{{ route('schedules.index', ['status' => 'all']) }}" 
               class="px-3 py-1.5 rounded-lg font-medium transition {{ $statusFilter === 'all' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white' }}">
                Semua ({{ $stats['total'] }})
            </a>
            <a href="{{ route('schedules.index', ['status' => 'pending']) }}" 
               class="px-3 py-1.5 rounded-lg font-medium transition {{ $statusFilter === 'pending' ? 'bg-amber-600 text-white' : 'text-gray-400 hover:text-white' }}">
                Pending ({{ $stats['pending'] }})
            </a>
            <a href="{{ route('schedules.index', ['status' => 'completed']) }}" 
               class="px-3 py-1.5 rounded-lg font-medium transition {{ $statusFilter === 'completed' ? 'bg-emerald-600 text-white' : 'text-gray-400 hover:text-white' }}">
                Completed ({{ $stats['completed'] }})
            </a>
            <a href="{{ route('schedules.index', ['status' => 'failed']) }}" 
               class="px-3 py-1.5 rounded-lg font-medium transition {{ $statusFilter === 'failed' ? 'bg-red-600 text-white' : 'text-gray-400 hover:text-white' }}">
                Failed ({{ $stats['failed'] }})
            </a>
        </div>
    </div>

    <!-- Table Queue -->
    <div class="card-dark rounded-2xl overflow-hidden shadow-xl border border-gray-800">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-300">
                <thead class="bg-gray-900/80 text-xs uppercase tracking-wider text-gray-400 border-b border-gray-800">
                    <tr>
                        <th class="px-6 py-4">Item Code / Project</th>
                        <th class="px-6 py-4">Portofolio Target</th>
                        <th class="px-6 py-4">Media (Multi-Image)</th>
                        <th class="px-6 py-4">Tanggal & Jam Tayang</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($schedules as $item)
                        <tr class="hover:bg-gray-800/40 transition">
                            <!-- Code & Project -->
                            <td class="px-6 py-4">
                                <div class="font-mono font-semibold text-white text-xs">{{ $item->item_code }}</div>
                                @if($item->project)
                                    <span class="inline-block mt-1 text-[11px] px-2 py-0.5 bg-indigo-950 text-indigo-300 border border-indigo-800 rounded font-medium">
                                        <i class="fa-solid fa-folder text-[10px] mr-1"></i>{{ $item->project->name }}
                                    </span>
                                @endif
                            </td>

                            <!-- Portfolio -->
                            <td class="px-6 py-4">
                                <span class="font-semibold text-indigo-300">{{ $item->portfolio_name }}</span>
                            </td>

                            <!-- Media Previews -->
                            <td class="px-6 py-4">
                                @php
                                    $paths = $item->media_paths ?? [$item->media_path];
                                @endphp
                                <div class="flex items-center space-x-1">
                                    @foreach(array_slice($paths, 0, 3) as $p)
                                        <img src="{{ asset($p) }}" class="w-9 h-9 object-cover rounded-lg border border-gray-700 shadow-sm">
                                    @endforeach
                                    @if(count($paths) > 3)
                                        <span class="w-9 h-9 bg-gray-800 rounded-lg flex items-center justify-center text-xs font-bold text-gray-400">
                                            +{{ count($paths) - 3 }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Target Date & Time -->
                            <td class="px-6 py-4">
                                <div class="font-semibold text-white">{{ $item->target_date->format('d M Y') }}</div>
                                <div class="text-xs text-gray-400"><i class="fa-regular fa-clock text-indigo-400 mr-1"></i>{{ $item->target_time }} WIB</div>
                            </td>

                            <!-- Status Badge -->
                            <td class="px-6 py-4">
                                @if($item->status === 'pending')
                                    <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-amber-950 text-amber-300 border border-amber-800">
                                        PENDING
                                    </span>
                                @elseif($item->status === 'completed')
                                    <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-emerald-950 text-emerald-300 border border-emerald-800">
                                        COMPLETED
                                    </span>
                                @elseif($item->status === 'processing')
                                    <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-blue-950 text-blue-300 border border-blue-800 animate-pulse">
                                        PROCESSING
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-red-950 text-red-300 border border-red-800">
                                        FAILED
                                    </span>
                                @endif
                            </td>

                            <!-- Action Buttons -->
                            <td class="px-6 py-4 text-right">
                                <button onclick="deleteSchedule({{ $item->id }})" 
                                        class="p-2 bg-red-950/40 text-red-400 hover:bg-red-900/60 rounded-lg transition border border-red-900/50" 
                                        title="Hapus item">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                Belum ada antrean jadwal Story. Buat Project Campaign baru untuk mengisi antrean rolling buffer 30 hari.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($schedules->hasPages())
            <div class="p-4 border-t border-gray-800">
                {{ $schedules->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    function deleteSchedule(id) {
        Swal.fire({
            title: 'Hapus Item Jadwal?',
            text: 'Jadwal ini akan dihapus dari antrean local.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
            customClass: {
                popup: 'swal2-popup-dark',
                title: 'swal2-title-dark',
                htmlContainer: 'swal2-html-dark'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                showLoading('Menghapus...', 'Menghapus item jadwal dari antrean...');

                fetch(`/schedules/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'Berhasil Dihapus', data.message);
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showAlert('error', 'Gagal Menghapus', data.message);
                    }
                })
                .catch(err => {
                    showAlert('error', 'Kesalahan Sistem', err.message);
                });
            }
        });
    }
</script>
@endsection
