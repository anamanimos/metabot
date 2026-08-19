@extends('layouts.app')

@section('title', 'Antrean Penjadwalan Story')

@section('content')
<div class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center space-x-2">
                <i class="fa-solid fa-calendar-days text-indigo-500"></i>
                <span>Antrean Penjadwalan Story</span>
            </h1>
            <p class="text-sm text-gray-400 mt-1">
                Daftar slot jadwal posting otomatis berbasis **Rolling 29-Day Buffer**.
            </p>
        </div>
    </div>

    <!-- Stats Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card-dark p-5 rounded-2xl flex items-center justify-between shadow">
            <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Total Antrean</p>
                <h3 class="text-2xl font-bold text-white mt-1" id="statTotal">{{ $stats['total'] }}</h3>
            </div>
            <div class="p-3 bg-indigo-900/40 text-indigo-400 rounded-xl">
                <i class="fa-solid fa-list-check text-xl"></i>
            </div>
        </div>

        <div class="card-dark p-5 rounded-2xl flex items-center justify-between shadow">
            <div>
                <p class="text-xs font-medium text-amber-400 uppercase tracking-wider">Pending (Siap Kirim)</p>
                <h3 class="text-2xl font-bold text-amber-400 mt-1" id="statPending">{{ $stats['pending'] }}</h3>
            </div>
            <div class="p-3 bg-amber-900/40 text-amber-400 rounded-xl">
                <i class="fa-solid fa-clock text-xl"></i>
            </div>
        </div>

        <div class="card-dark p-5 rounded-2xl flex items-center justify-between shadow">
            <div>
                <p class="text-xs font-medium text-emerald-400 uppercase tracking-wider">Completed</p>
                <h3 class="text-2xl font-bold text-emerald-400 mt-1" id="statCompleted">{{ $stats['completed'] }}</h3>
            </div>
            <div class="p-3 bg-emerald-900/40 text-emerald-400 rounded-xl">
                <i class="fa-solid fa-circle-check text-xl"></i>
            </div>
        </div>

        <div class="card-dark p-5 rounded-2xl flex items-center justify-between shadow">
            <div>
                <p class="text-xs font-medium text-red-400 uppercase tracking-wider">Failed</p>
                <h3 class="text-2xl font-bold text-red-400 mt-1" id="statFailed">{{ $stats['failed'] }}</h3>
            </div>
            <div class="p-3 bg-red-900/40 text-red-400 rounded-xl">
                <i class="fa-solid fa-triangle-exclamation text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Main Schedule Table Card -->
    <div class="card-dark rounded-2xl overflow-hidden shadow-xl border border-gray-800 space-y-4">
        <!-- Table Header & Filter Tabs -->
        <div class="p-6 pb-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="text-lg font-bold text-white flex items-center space-x-2">
                <i class="fa-solid fa-table-list text-indigo-400"></i>
                <span>Daftar Slot Penjadwalan</span>
            </h2>

            <!-- Status Filter Tabs -->
            <div class="flex items-center space-x-1 bg-gray-900 p-1 rounded-xl border border-gray-800 text-xs">
                <a href="{{ route('schedules.index', ['status' => 'all']) }}" 
                   class="px-3 py-1.5 rounded-lg font-semibold transition {{ $statusFilter === 'all' ? 'bg-indigo-600 text-white shadow' : 'text-gray-400 hover:text-white' }}">
                    Semua ({{ $stats['total'] }})
                </a>
                <a href="{{ route('schedules.index', ['status' => 'pending']) }}" 
                   class="px-3 py-1.5 rounded-lg font-semibold transition {{ $statusFilter === 'pending' ? 'bg-amber-600 text-white shadow' : 'text-gray-400 hover:text-white' }}">
                    Pending ({{ $stats['pending'] }})
                </a>
                <a href="{{ route('schedules.index', ['status' => 'completed']) }}" 
                   class="px-3 py-1.5 rounded-lg font-semibold transition {{ $statusFilter === 'completed' ? 'bg-emerald-600 text-white shadow' : 'text-gray-400 hover:text-white' }}">
                    Completed ({{ $stats['completed'] }})
                </a>
            </div>
        </div>

        <!-- Table Content -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-300">
                <thead class="bg-gray-900/80 text-xs uppercase tracking-wider text-gray-400 border-b border-gray-800">
                    <tr>
                        <th class="px-6 py-4">Item Code / Project</th>
                        <th class="px-6 py-4">Portfolio Meta</th>
                        <th class="px-6 py-4">Media Preview</th>
                        <th class="px-6 py-4">Target Tayang</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800" id="scheduleTableBody">
                    @forelse($schedules as $item)
                        <tr class="hover:bg-gray-800/40 transition" id="schedule-row-{{ $item->id }}">
                            <!-- Item Code & Project Name -->
                            <td class="px-6 py-4">
                                <div class="font-mono text-xs font-semibold text-white">{{ $item->item_code }}</div>
                                @if($item->project)
                                    <a href="{{ route('projects.show', $item->project->id) }}" class="text-xs text-indigo-400 hover:underline">
                                        {{ $item->project->name }}
                                    </a>
                                @else
                                    <span class="text-xs text-gray-500">Standalone Item</span>
                                @endif
                            </td>

                            <!-- Portfolio -->
                            <td class="px-6 py-4">
                                <span class="font-semibold text-indigo-300">{{ $item->portfolio_name }}</span>
                            </td>

                            <!-- Media Previews -->
                            <td class="px-6 py-4">
                                @php
                                    $urls = $item->media_urls;
                                @endphp
                                <div class="flex items-center space-x-1">
                                    @foreach(array_slice($urls, 0, 3) as $u)
                                        <img src="{{ $u }}" class="w-9 h-9 object-cover rounded-lg border border-gray-700 shadow-sm">
                                    @endforeach
                                    @if(count($urls) > 3)
                                        <span class="w-9 h-9 bg-gray-800 rounded-lg flex items-center justify-center text-xs font-bold text-gray-400">
                                            +{{ count($urls) - 3 }}
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
                                Belum ada antrean jadwal Story. Buat Project Campaign baru untuk mengisi antrean rolling buffer.
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
            text: "Item jadwal ini akan dihapus dari antrean.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            customClass: {
                popup: 'swal2-popup-dark',
                title: 'swal2-title-dark',
                htmlContainer: 'swal2-html-dark'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`/schedules/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById(`schedule-row-${id}`)?.remove();
                        showAlert('success', 'Terhapus!', data.message);
                        if (data.stats) {
                            document.getElementById('statTotal').textContent = data.stats.total;
                            document.getElementById('statPending').textContent = data.stats.pending;
                            document.getElementById('statCompleted').textContent = data.stats.completed;
                            document.getElementById('statFailed').textContent = data.stats.failed;
                        }
                    } else {
                        showAlert('error', 'Gagal', data.message);
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
