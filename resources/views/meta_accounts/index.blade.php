@extends('layouts.app')

@section('title', 'Manajemen Akun Meta')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center space-x-2">
                <i class="fa-solid fa-users text-indigo-500"></i>
                <span>Akun Meta (Facebook / Instagram)</span>
            </h1>
            <p class="text-sm text-gray-400 mt-1">
                Kelola status sesi login akun Meta & impor berkas otentikasi lintas-platform (`state.json`).
            </p>
        </div>

        <button onclick="openNewAccountModal()" 
                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl shadow-lg transition flex items-center justify-center space-x-2">
            <i class="fa-solid fa-user-plus"></i>
            <span>Tambah Akun Meta Baru</span>
        </button>
    </div>

    <!-- Accounts Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($accounts as $account)
            <div class="card-dark rounded-2xl p-6 space-y-4 shadow-lg border border-gray-800 flex flex-col justify-between hover:border-indigo-500/50 transition" id="account-card-{{ $account->id }}">
                <div class="space-y-3">
                    <!-- Card Top Header -->
                    <div class="flex items-center justify-between">
                        <div class="p-3 bg-gradient-to-tr from-indigo-600 to-purple-600 rounded-xl text-white font-bold text-lg">
                            <i class="fa-brands fa-facebook-f"></i>
                        </div>

                        <!-- Login Status Badge -->
                        <span id="account-status-badge-{{ $account->id }}" 
                              class="px-3 py-1 text-xs font-extrabold rounded-full uppercase tracking-wider flex items-center space-x-1.5 {{ $account->status === 'active' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-red-950 text-red-300 border border-red-800' }}">
                            <span class="w-2 h-2 rounded-full {{ $account->status === 'active' ? 'bg-emerald-400 animate-pulse' : 'bg-red-400' }}"></span>
                            <span>{{ $account->status === 'active' ? 'TERHUBUNG' : 'BELUM LOGIN' }}</span>
                        </span>
                    </div>

                    <!-- Account Name & Folder -->
                    <div>
                        <a href="{{ route('meta-accounts.show', $account->id) }}" class="font-bold text-lg text-white leading-snug hover:text-indigo-400 transition flex items-center space-x-1.5">
                            <span>{{ $account->account_name }}</span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-xs text-gray-500"></i>
                        </a>
                        <p class="text-xs text-gray-400 mt-0.5 font-mono">
                            <i class="fa-regular fa-folder text-indigo-400 mr-1"></i>{{ $account->session_folder }}
                        </p>
                    </div>

                    <!-- Stats Counts -->
                    <div class="pt-2 border-t border-gray-800 grid grid-cols-2 gap-2 text-xs text-gray-400">
                        <div>
                            <span>Aset Bisnis:</span>
                            <p class="font-bold text-white mt-0.5"><i class="fa-solid fa-briefcase text-indigo-400 mr-1"></i>{{ $account->portfolios_count }} Aset</p>
                        </div>
                        <div>
                            <span>Project Terhubung:</span>
                            <p class="font-bold text-white mt-0.5"><i class="fa-solid fa-layer-group text-indigo-400 mr-1"></i>{{ $account->projects_count }} Project</p>
                        </div>
                    </div>
                </div>

                <!-- Card Actions Area -->
                <div class="pt-4 border-t border-gray-800 space-y-2">
                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="checkAccountStatus({{ $account->id }})" 
                                class="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs font-semibold rounded-xl border border-gray-700 transition flex items-center justify-center space-x-1">
                            <i class="fa-solid fa-rotate text-indigo-400"></i>
                            <span>Cek Status</span>
                        </button>

                        <button onclick="openImportStateModal({{ $account->id }}, '{{ $account->account_name }}')" 
                                class="px-3 py-2 bg-indigo-950/60 hover:bg-indigo-900/80 text-indigo-300 text-xs font-semibold rounded-xl border border-indigo-800 transition flex items-center justify-center space-x-1">
                            <i class="fa-solid fa-key"></i>
                            <span>Import Sesi</span>
                        </button>
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <a href="{{ route('meta-accounts.show', $account->id) }}" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold flex items-center space-x-1">
                            <span>Lihat Detail</span>
                            <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </a>

                        <button onclick="deleteAccount({{ $account->id }}, '{{ $account->account_name }}')" 
                                class="px-3 py-1.5 bg-red-950/40 text-red-400 hover:bg-red-900/60 text-xs font-semibold rounded-lg border border-red-900/60 transition flex items-center space-x-1.5">
                            <i class="fa-solid fa-trash-can text-[11px]"></i>
                            <span>Hapus</span>
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Modal Tambah Akun Baru -->
<div id="modalNewAccount" class="fixed inset-0 z-50 hidden bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="card-dark w-full max-w-md rounded-2xl shadow-2xl border border-gray-800 p-6 space-y-5 relative">
        <div class="flex items-center justify-between border-b border-gray-800 pb-3">
            <h3 class="text-lg font-bold text-white flex items-center space-x-2">
                <i class="fa-solid fa-user-plus text-indigo-400"></i>
                <span>Tambah Akun Meta Baru</span>
            </h3>
            <button onclick="closeNewAccountModal()" class="text-gray-400 hover:text-white transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form id="formNewAccount" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1">Nama / Label Akun Meta</label>
                <input type="text" name="account_name" required placeholder="Contoh: Akun Sevencols Utama" 
                       class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div class="pt-3 border-t border-gray-800 flex justify-end space-x-3">
                <button type="button" onclick="closeNewAccountModal()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 font-semibold rounded-xl text-xs transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl text-xs transition shadow-lg flex items-center space-x-2">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Simpan Akun</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Import Sesi Cookie state.json -->
<div id="modalImportState" class="fixed inset-0 z-50 hidden bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="card-dark w-full max-w-lg rounded-2xl shadow-2xl border border-gray-800 p-6 space-y-5 relative">
        <div class="flex items-center justify-between border-b border-gray-800 pb-3">
            <h3 class="text-lg font-bold text-white flex items-center space-x-2">
                <i class="fa-solid fa-key text-indigo-400"></i>
                <span>Import Sesi Login Meta (<span id="modalImportAccountTitle" class="text-indigo-300"></span>)</span>
            </h3>
            <button onclick="closeImportStateModal()" class="text-gray-400 hover:text-white transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form id="formImportState" class="space-y-4" enctype="multipart/form-data">
            <input type="hidden" id="importAccountId" name="account_id">

            <div>
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Pilihan 1: Unggah File state.json (Playwright Cookie Format)</label>
                <input type="file" name="state_file" accept=".json" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500">
            </div>

            <div class="relative flex py-1 items-center">
                <div class="flex-grow border-t border-gray-800"></div>
                <span class="flex-shrink mx-4 text-xs font-semibold text-gray-500 uppercase">atau</span>
                <div class="flex-grow border-t border-gray-800"></div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Pilihan 2: Tempel Teks JSON Cookies</label>
                <textarea name="state_json" rows="4" placeholder='{"cookies": [{"name": "c_user", "value": "..."}, ...]}' 
                          class="w-full bg-gray-900 border border-gray-700 rounded-xl p-3 text-xs font-mono text-white focus:outline-none focus:border-indigo-500"></textarea>
            </div>

            <div class="pt-3 border-t border-gray-800 flex justify-end space-x-3">
                <button type="button" onclick="closeImportStateModal()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 font-semibold rounded-xl text-xs transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl text-xs transition shadow-lg flex items-center space-x-2">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Simpan & Hubungkan Sesi</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openNewAccountModal() {
        document.getElementById('modalNewAccount').classList.remove('hidden');
    }

    function closeNewAccountModal() {
        document.getElementById('modalNewAccount').classList.add('hidden');
    }

    function openImportStateModal(id, accountName) {
        document.getElementById('importAccountId').value = id;
        document.getElementById('modalImportAccountTitle').textContent = accountName;
        document.getElementById('modalImportState').classList.remove('hidden');
    }

    function closeImportStateModal() {
        document.getElementById('modalImportState').classList.add('hidden');
    }

    // Cek Status Login Akun Live
    function checkAccountStatus(id) {
        showLoading('Memeriksa Status Login...', 'Memeriksa validitas cookie sesi Facebook/Meta...');

        fetch(`/meta-accounts/${id}/check-status`, {
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.is_logged_in) {
                showAlert('success', 'Akun Terhubung!', data.message);
            } else {
                showAlert('warning', 'Belum Login', data.message);
            }
            setTimeout(() => window.location.reload(), 1500);
        })
        .catch(err => {
            showAlert('error', 'Kesalahan Sistem', err.message);
        });
    }

    // Submit Import Sesi State
    document.getElementById('formImportState').addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('importAccountId').value;
        const formData = new FormData(this);

        showLoading('Mengimpor Sesi Login...', 'Menyimpan berkas otentikasi sesi Meta...');

        fetch(`/meta-accounts/${id}/import-state`, {
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
                showAlert('success', 'Berhasil Terhubung!', data.message);
                closeImportStateModal();
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showAlert('error', 'Gagal Impor', data.message);
            }
        })
        .catch(err => {
            showAlert('error', 'Kesalahan Sistem', err.message);
        });
    });

    document.getElementById('formNewAccount').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        showLoading('Menyiapkan Akun Baru...', 'Menyimpan data akun Meta baru...');

        fetch("{{ route('meta-accounts.store') }}", {
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
                showAlert('success', 'Akun Tersimpan!', data.message);
                closeNewAccountModal();
                setTimeout(() => window.location.reload(), 1200);
            } else {
                showAlert('error', 'Gagal Menambah Akun', data.message);
            }
        })
        .catch(err => {
            showAlert('error', 'Kesalahan Sistem', err.message);
        });
    });

    function deleteAccount(id, accountName) {
        Swal.fire({
            title: 'Hapus Akun Meta?',
            text: `Akun '${accountName}' beserta data portofolionya akan dihapus dari sistem.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus Akun',
            cancelButtonText: 'Batal',
            customClass: {
                popup: 'swal2-popup-dark',
                title: 'swal2-title-dark',
                htmlContainer: 'swal2-html-dark'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                showLoading('Menghapus Akun...', `Menghapus akun Meta '${accountName}'...`);

                fetch(`/meta-accounts/${id}`, {
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
                        setTimeout(() => window.location.reload(), 1200);
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
