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
                Kelola status sesi akun Meta, pindaian portofolio per akun, & Login Tab Manual 1-Klik.
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
                            <span class="w-2 h-2 rounded-full {{ $account->status === 'active' ? 'bg-emerald-400 animate-pulse' : 'bg-red-400' }}" id="account-status-dot-{{ $account->id }}"></span>
                            <span id="account-status-text-{{ $account->id }}">{{ $account->status === 'active' ? 'TERHUBUNG' : 'BELUM LOGIN' }}</span>
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
                            <p class="font-bold text-white mt-0.5"><i class="fa-solid fa-briefcase text-indigo-400 mr-1"></i><span id="account-portfolio-count-{{ $account->id }}">{{ $account->portfolios_count }}</span> Aset</p>
                        </div>
                        <div>
                            <span>Project Terhubung:</span>
                            <p class="font-bold text-white mt-0.5"><i class="fa-solid fa-layer-group text-indigo-400 mr-1"></i>{{ $account->projects_count }} Project</p>
                        </div>
                    </div>
                </div>

                <!-- Card Actions Area -->
                <div class="pt-4 border-t border-gray-800 space-y-2">
                    <!-- Tombol Utama Ambil Portofolio Khusus Akun Ini -->
                    <button onclick="fetchAccountPortfolios({{ $account->id }}, '{{ $account->account_name }}')" 
                            class="w-full py-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-bold rounded-xl shadow transition flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        <span>Ambil Portofolio Akun Ini</span>
                    </button>

                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="checkAccountStatus({{ $account->id }}, '{{ $account->account_name }}')" 
                                class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs font-semibold rounded-xl border border-gray-700 transition flex items-center justify-center space-x-1">
                            <i class="fa-solid fa-eye text-indigo-400"></i>
                            <span>Cek Status Live</span>
                        </button>

                        <button onclick="openImportStateModal({{ $account->id }}, '{{ $account->account_name }}')" 
                                id="btn-relogin-{{ $account->id }}"
                                class="px-3 py-1.5 text-xs font-semibold rounded-xl transition flex items-center justify-center space-x-1 {{ $account->status === 'active' ? 'bg-indigo-950/60 hover:bg-indigo-900/80 text-indigo-300 border border-indigo-800' : 'bg-amber-600 hover:bg-amber-500 text-white shadow-lg font-bold border border-amber-400 animate-pulse' }}">
                            <i class="fa-solid fa-key"></i>
                            <span>{{ $account->status === 'active' ? 'Login Sesi' : '🔑 Login Ulang' }}</span>
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

<!-- Modal Login Tab Manual / Import Sesi Cookie state.json -->
<div id="modalImportState" class="fixed inset-0 z-50 hidden bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="card-dark w-full max-w-lg rounded-2xl shadow-2xl border border-gray-800 p-6 space-y-5 relative">
        <div class="flex items-center justify-between border-b border-gray-800 pb-3">
            <h3 class="text-lg font-bold text-white flex items-center space-x-2">
                <i class="fa-solid fa-key text-amber-400"></i>
                <span>🔑 Hubungkan Sesi Meta (<span id="modalImportAccountTitle" class="text-amber-300"></span>)</span>
            </h3>
            <button onclick="closeImportStateModal()" class="text-gray-400 hover:text-white transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Navigation Sub-Tabs -->
        <div class="flex border-b border-gray-800 text-xs font-semibold">
            <button type="button" onclick="switchLoginTab('openTab')" id="tabBtnOpenTab" class="py-2.5 px-4 text-indigo-400 border-b-2 border-indigo-500 font-bold transition flex items-center space-x-2">
                <i class="fa-solid fa-up-right-from-square"></i>
                <span>Metode 1: Buka Tab Facebook & Connect</span>
            </button>
            <button type="button" onclick="switchLoginTab('direct')" id="tabBtnDirect" class="py-2.5 px-4 text-gray-400 hover:text-white transition flex items-center space-x-2">
                <i class="fa-solid fa-right-to-bracket"></i>
                <span>Metode 2: Form Login Web</span>
            </button>
            <button type="button" onclick="switchLoginTab('file')" id="tabBtnFile" class="py-2.5 px-4 text-gray-400 hover:text-white transition flex items-center space-x-2">
                <i class="fa-solid fa-file-import"></i>
                <span>Metode 3: state.json</span>
            </button>
        </div>

        <!-- TAB 1: BUKA TAB FACEBOOK LOGIN & CONNECT 1-KLIK -->
        <div id="tabOpenTab" class="space-y-4">
            <div class="bg-indigo-950/60 border border-indigo-800 p-4 rounded-xl text-xs text-indigo-200 space-y-3">
                <p class="font-bold flex items-center space-x-2 text-indigo-300 text-sm">
                    <i class="fa-solid fa-arrow-pointer text-amber-400"></i>
                    <span>Langkah Mudah Login Manual via Tab Browser:</span>
                </p>
                <ol class="list-decimal list-inside space-y-2 text-[11px] text-indigo-200 leading-relaxed">
                    <li>Klik tombol hijau <b>"Buka Tab Facebook Login"</b> di bawah untuk membuka Facebook di tab baru browser Anda.</li>
                    <li>Lakukan login manual ke akun Meta / Facebook Anda seperti biasa di tab baru tersebut.</li>
                    <li>Setelah login di Facebook, klik <b>"Salin Script 1-Klik"</b> di bawah ini, lalu buka Console DevTools (F12) pada tab Facebook dan paste & tekan Enter. Sesi akan otomatis terhubung tanpa error 419!</li>
                </ol>
            </div>

            <div class="space-y-2 pt-2">
                <a href="https://business.facebook.com/latest/home" target="_blank" 
                   class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs shadow-lg transition flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-up-right-from-square"></i>
                    <span>1. Buka Tab Facebook Business Suite (Login Manual)</span>
                </a>

                <!-- Bookmarklet Link Drag Button -->
                <a id="bookmarkletLink" href="#" onclick="alert('Seret (drag) tombol ini ke Bookmark Bar browser Anda, lalu klik tombol ini saat membuka tab Facebook!')"
                   class="w-full py-2.5 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-xs shadow transition flex items-center justify-center space-x-2 cursor-grab">
                    <i class="fa-solid fa-bookmark"></i>
                    <span>📌 2. Drag Tombol Ini ke Bookmark Bar (Hubungkan Sesi)</span>
                </a>

                <button onclick="copyBookmarkletScript()" 
                        class="w-full py-2 bg-indigo-950 hover:bg-indigo-900 text-indigo-200 font-bold rounded-xl text-xs border border-indigo-700 transition flex items-center justify-center space-x-2 shadow">
                    <i class="fa-solid fa-copy text-amber-400"></i>
                    <span>Salin Script 1-Klik (Async Fetch Tanpa Error 419)</span>
                </button>
            </div>
        </div>

        <!-- TAB 2: FORM LOGIN LANGSUNG VIA WEB UI -->
        <form id="formDirectLogin" class="space-y-4 hidden">
            <input type="hidden" id="directLoginAccountId" name="account_id">
            
            <div class="bg-indigo-950/50 border border-indigo-800 p-3 rounded-xl text-xs text-indigo-200 space-y-1">
                <p class="font-bold flex items-center space-x-1.5 text-indigo-300">
                    <i class="fa-solid fa-bolt text-amber-400"></i>
                    <span>Login Otomatis via Web UI:</span>
                </p>
                <p class="text-[11px] text-indigo-300/90 leading-relaxed">
                    Masukkan Email & Kata Sandi Facebook Anda. Bot Playwright akan melakukan login otomatis di server dan menyimpan sesi secara langsung!
                </p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1">Email / No. HP Facebook</label>
                <input type="text" name="email" required placeholder="contoh: email@domain.com atau 081234..." 
                       class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1">Kata Sandi (Password) Facebook</label>
                <input type="password" name="password" required placeholder="Masukkan kata sandi Facebook Anda" 
                       class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1">Kode 2FA / Authenticator (Opsional)</label>
                <input type="text" name="two_factor" placeholder="Masukkan 6-digit kode OTP/2FA jika akun aktifkan 2FA" 
                       class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono tracking-widest">
            </div>

            <div class="pt-3 border-t border-gray-800 flex justify-end space-x-3">
                <button type="button" onclick="closeImportStateModal()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 font-semibold rounded-xl text-xs transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold rounded-xl text-xs transition shadow-lg flex items-center space-x-2">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Proses Login Langsung</span>
                </button>
            </div>
        </form>

        <!-- TAB 3: UNGGAH FILE STATE.JSON -->
        <form id="formImportState" class="space-y-4 hidden" enctype="multipart/form-data">
            <input type="hidden" id="importAccountId" name="account_id">

            <div>
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Unggah File state.json (Playwright Cookie Format)</label>
                <input type="file" name="state_file" accept=".json" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500">
            </div>

            <div class="relative flex py-1 items-center">
                <div class="flex-grow border-t border-gray-800"></div>
                <span class="flex-shrink mx-4 text-xs font-semibold text-gray-500 uppercase">atau</span>
                <div class="flex-grow border-t border-gray-800"></div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Tempel Teks JSON Cookies</label>
                <textarea name="state_json" rows="4" placeholder='{"cookies": [{"name": "c_user", "value": "..."}, ...]}' 
                          class="w-full bg-gray-900 border border-gray-700 rounded-xl p-3 text-xs font-mono text-white focus:outline-none focus:border-indigo-500"></textarea>
            </div>

            <div class="pt-3 border-t border-gray-800 flex justify-end space-x-3">
                <button type="button" onclick="closeImportStateModal()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 font-semibold rounded-xl text-xs transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs transition shadow-lg flex items-center space-x-2">
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
    let currentModalAccountId = 1;

    function openNewAccountModal() {
        document.getElementById('modalNewAccount').classList.remove('hidden');
    }

    function closeNewAccountModal() {
        document.getElementById('modalNewAccount').classList.add('hidden');
    }

    function switchLoginTab(tab) {
        const btnOpenTab = document.getElementById('tabBtnOpenTab');
        const btnDirect = document.getElementById('tabBtnDirect');
        const btnFile = document.getElementById('tabBtnFile');
        
        const tabOpenTab = document.getElementById('tabOpenTab');
        const formDirect = document.getElementById('formDirectLogin');
        const formFile = document.getElementById('formImportState');

        btnOpenTab.className = "py-2.5 px-4 text-gray-400 hover:text-white transition flex items-center space-x-2";
        btnDirect.className = "py-2.5 px-4 text-gray-400 hover:text-white transition flex items-center space-x-2";
        btnFile.className = "py-2.5 px-4 text-gray-400 hover:text-white transition flex items-center space-x-2";

        tabOpenTab.classList.add('hidden');
        formDirect.classList.add('hidden');
        formFile.classList.add('hidden');

        if (tab === 'openTab') {
            btnOpenTab.className = "py-2.5 px-4 text-indigo-400 border-b-2 border-indigo-500 font-bold transition flex items-center space-x-2";
            tabOpenTab.classList.remove('hidden');
        } else if (tab === 'direct') {
            btnDirect.className = "py-2.5 px-4 text-indigo-400 border-b-2 border-indigo-500 font-bold transition flex items-center space-x-2";
            formDirect.classList.remove('hidden');
        } else {
            btnFile.className = "py-2.5 px-4 text-indigo-400 border-b-2 border-indigo-500 font-bold transition flex items-center space-x-2";
            formFile.classList.remove('hidden');
        }
    }

    function updateBookmarkletHref(id) {
        const scriptCode = `javascript:(function(){var cookies=document.cookie.split(';').map(function(c){var p=c.trim().split('=');return{name:p[0],value:p.slice(1).join('='),domain:'.facebook.com',path:'/'};});var stateData={cookies:cookies,origins:[]};var formData=new FormData();formData.append('state_json',JSON.stringify(stateData));fetch('https://meta.damaijaya.my.id/meta-accounts/${id}/import-state',{method:'POST',body:formData}).then(function(res){return res.json();}).then(function(data){if(data.success){alert('✅ SESI BERHASIL TERHUBUNG! Sesi Meta Account Anda telah aktif.');}else{alert('❌ Gagal: '+data.message);}}).catch(function(err){alert('❌ Error: '+err.message);});})();`;
        document.getElementById('bookmarkletLink').href = scriptCode;
    }

    function copyBookmarkletScript() {
        const id = currentModalAccountId;
        const scriptCode = `javascript:(function(){var cookies=document.cookie.split(';').map(function(c){var p=c.trim().split('=');return{name:p[0],value:p.slice(1).join('='),domain:'.facebook.com',path:'/'};});var stateData={cookies:cookies,origins:[]};var formData=new FormData();formData.append('state_json',JSON.stringify(stateData));fetch('https://meta.damaijaya.my.id/meta-accounts/${id}/import-state',{method:'POST',body:formData}).then(function(res){return res.json();}).then(function(data){if(data.success){alert('✅ SESI BERHASIL TERHUBUNG! Sesi Meta Account Anda telah aktif.');}else{alert('❌ Gagal: '+data.message);}}).catch(function(err){alert('❌ Error: '+err.message);});})();`;
        
        navigator.clipboard.writeText(scriptCode).then(() => {
            showAlert('success', 'Kode Async Berhasil Disalin!', 'Buka Console (F12) pada tab Facebook Anda, lalu paste & tekan Enter. Sesi akan otomatis terhubung tanpa error 419!');
        }).catch(err => {
            showAlert('error', 'Gagal Menyalin', err.message);
        });
    }

    function openImportStateModal(id, accountName) {
        currentModalAccountId = id;
        document.getElementById('importAccountId').value = id;
        document.getElementById('directLoginAccountId').value = id;
        document.getElementById('modalImportAccountTitle').textContent = accountName || '';
        updateBookmarkletHref(id);
        document.getElementById('modalImportState').classList.remove('hidden');
        switchLoginTab('openTab');
    }

    function closeImportStateModal() {
        document.getElementById('modalImportState').classList.add('hidden');
    }

    // Submit Direct Web UI Login
    document.getElementById('formDirectLogin').addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('directLoginAccountId').value;
        const formData = new FormData(this);

        showLoading('Memproses Login Meta...', 'Membuka Chromium browser untuk mengautentikasi Email & Password ke Facebook Meta...');

        fetch(`/meta-accounts/${id}/direct-login`, {
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
                showAlert('success', 'Berhasil Login!', data.message);
                closeImportStateModal();

                const badge = document.getElementById(`account-status-badge-${id}`);
                const dot = document.getElementById(`account-status-dot-${id}`);
                const text = document.getElementById(`account-status-text-${id}`);
                const btnRelogin = document.getElementById(`btn-relogin-${id}`);
                if (badge && dot && text) {
                    badge.className = "px-3 py-1 text-xs font-extrabold rounded-full uppercase tracking-wider flex items-center space-x-1.5 bg-emerald-950 text-emerald-300 border border-emerald-800";
                    dot.className = "w-2 h-2 rounded-full bg-emerald-400 animate-pulse";
                    text.textContent = "TERHUBUNG";
                    if (btnRelogin) {
                        btnRelogin.className = "px-3 py-1.5 bg-indigo-950/60 hover:bg-indigo-900/80 text-indigo-300 text-xs font-semibold rounded-xl border border-indigo-800 transition flex items-center justify-center space-x-1";
                        btnRelogin.innerHTML = '<i class="fa-solid fa-key"></i><span>Login Sesi</span>';
                    }
                }
            } else {
                showAlert('error', 'Gagal Login', data.message);
            }
        })
        .catch(err => {
            showAlert('error', 'Kesalahan Sistem', err.message);
        });
    });

    // Ambil Portofolio Khusus Per Akun Meta
    function fetchAccountPortfolios(id, accountName) {
        showLoading('Memindai Portofolio...', `Membuka bot Chromium untuk memindai Aset Bisnis akun '${accountName}'...`);

        fetch(`/meta-accounts/${id}/fetch-portfolios`, {
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
                if (data.count !== undefined) {
                    const el = document.getElementById(`account-portfolio-count-${id}`);
                    if (el) el.textContent = data.count;
                }
            } else {
                showAlert('info', 'Info Pemindaian', data.message);
            }
        })
        .catch(err => {
            showAlert('error', 'Kesalahan Sistem', err.message || 'Gagal memindai portofolio.');
        });
    }

    // Cek Status Login Akun Live
    function checkAccountStatus(id, accountName) {
        showLoading('Memeriksa Status Login Meta...', 'Membuka Chromium browser untuk mengambil tangkapan layar Meta Business Suite real-time...');

        fetch(`/meta-accounts/${id}/check-status`, {
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById(`account-status-badge-${id}`);
            const dot = document.getElementById(`account-status-dot-${id}`);
            const text = document.getElementById(`account-status-text-${id}`);
            const btnRelogin = document.getElementById(`btn-relogin-${id}`);

            if (badge && dot && text) {
                if (data.is_logged_in) {
                    badge.className = "px-3 py-1 text-xs font-extrabold rounded-full uppercase tracking-wider flex items-center space-x-1.5 bg-emerald-950 text-emerald-300 border border-emerald-800";
                    dot.className = "w-2 h-2 rounded-full bg-emerald-400 animate-pulse";
                    text.textContent = "TERHUBUNG";
                    if (btnRelogin) {
                        btnRelogin.className = "px-3 py-1.5 bg-indigo-950/60 hover:bg-indigo-900/80 text-indigo-300 text-xs font-semibold rounded-xl border border-indigo-800 transition flex items-center justify-center space-x-1";
                        btnRelogin.innerHTML = '<i class="fa-solid fa-key"></i><span>Login Sesi</span>';
                    }
                } else {
                    badge.className = "px-3 py-1 text-xs font-extrabold rounded-full uppercase tracking-wider flex items-center space-x-1.5 bg-red-950 text-red-300 border border-red-800";
                    dot.className = "w-2 h-2 rounded-full bg-red-400";
                    text.textContent = "BELUM LOGIN";
                    if (btnRelogin) {
                        btnRelogin.className = "px-3 py-1.5 bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold rounded-xl border border-amber-400 shadow-lg animate-pulse transition flex items-center justify-center space-x-1";
                        btnRelogin.innerHTML = '<i class="fa-solid fa-key"></i><span>🔑 Login Ulang</span>';
                    }
                }
            }

            if (data.screenshot_url) {
                const actionButtonHtml = !data.is_logged_in ? `
                    <div class="pt-2">
                        <button onclick="Swal.close(); openImportStateModal(${id}, '${accountName}')" 
                                class="w-full py-2.5 bg-gradient-to-r from-amber-600 to-indigo-600 hover:from-amber-500 hover:to-indigo-500 text-white text-xs font-bold rounded-xl shadow-lg transition flex items-center justify-center space-x-2">
                            <i class="fa-solid fa-key text-sm"></i>
                            <span>🔑 Klik Di Sini Untuk Buka Tab Facebook & Connect Sesi</span>
                        </button>
                    </div>
                ` : '';

                Swal.fire({
                    icon: data.is_logged_in ? 'success' : 'warning',
                    title: data.is_logged_in ? 'Akun Meta Terhubung & Aktif!' : '⚠️ Sesi Meta Belum Login',
                    html: `
                        <div class="space-y-3">
                            <p class="text-xs text-indigo-300 font-semibold">${data.message}</p>
                            <div class="border border-gray-700 rounded-xl overflow-hidden shadow-2xl bg-gray-950">
                                <img src="${data.screenshot_url}?t=${new Date().getTime()}" class="w-full h-auto object-cover max-h-80">
                            </div>
                            ${actionButtonHtml}
                            <p class="text-[11px] text-gray-400 italic">Tangkapan layar di atas diambil secara real-time dari Meta Business Suite server Anda.</p>
                        </div>
                    `,
                    confirmButtonText: 'Tutup',
                    customClass: {
                        popup: 'swal2-popup-dark max-w-2xl',
                        title: 'swal2-title-dark',
                        htmlContainer: 'swal2-html-dark'
                    }
                });
            } else {
                showAlert(data.is_logged_in ? 'success' : 'warning', data.is_logged_in ? 'Akun Terhubung!' : 'Belum Login', data.message);
            }
        })
        .catch(err => {
            showAlert('error', 'Kesalahan Sistem', err.message);
        });
    }

    // Submit Import Sesi File state.json
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

                const badge = document.getElementById(`account-status-badge-${id}`);
                const dot = document.getElementById(`account-status-dot-${id}`);
                const text = document.getElementById(`account-status-text-${id}`);
                const btnRelogin = document.getElementById(`btn-relogin-${id}`);
                if (badge && dot && text) {
                    badge.className = "px-3 py-1 text-xs font-extrabold rounded-full uppercase tracking-wider flex items-center space-x-1.5 bg-emerald-950 text-emerald-300 border border-emerald-800";
                    dot.className = "w-2 h-2 rounded-full bg-emerald-400 animate-pulse";
                    text.textContent = "TERHUBUNG";
                    if (btnRelogin) {
                        btnRelogin.className = "px-3 py-1.5 bg-indigo-950/60 hover:bg-indigo-900/80 text-indigo-300 text-xs font-semibold rounded-xl border border-indigo-800 transition flex items-center justify-center space-x-1";
                        btnRelogin.innerHTML = '<i class="fa-solid fa-key"></i><span>Login Sesi</span>';
                    }
                }
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
