<x-layouts.app title="Data Mahasiswa">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('import.create') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Import Excel</a>
            <a href="{{ route('import.template') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Download Template</a>
        </div>
        <button type="button" onclick="document.getElementById('dialog-tambah-mahasiswa').showModal()"
                class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">
            + Tambah Mahasiswa
        </button>
    </div>

    <dialog id="dialog-tambah-mahasiswa" class="fixed left-1/2 top-1/2 w-full max-w-xl -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-slate-200 bg-white p-6 shadow-lg backdrop:bg-slate-900/60">
        <h3 class="mb-4 font-serif text-xl font-semibold text-slate-900">Tambah Mahasiswa</h3>
        <form method="POST" action="{{ route('mahasiswa.store') }}">
            @csrf
            @include('mahasiswa._form', ['kelasList' => $kelasList, 'mahasiswa' => null, 'idPrefix' => 'create-'])

            <div class="mt-6 flex gap-2">
                <button type="submit" class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Simpan</button>
                <button type="button" onclick="document.getElementById('dialog-tambah-mahasiswa').close()"
                        class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Batal
                </button>
            </div>
        </form>
    </dialog>

    <dialog id="dialog-edit-mahasiswa" class="fixed left-1/2 top-1/2 w-full max-w-xl -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-slate-200 bg-white p-6 shadow-lg backdrop:bg-slate-900/60">
        <h3 class="mb-4 font-serif text-xl font-semibold text-slate-900">Edit Mahasiswa</h3>
        <form method="POST" action="{{ old('_edit_id') ? route('mahasiswa.update', old('_edit_id')) : '#' }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="_edit_id" value="{{ old('_edit_id') }}">
            @include('mahasiswa._form', ['kelasList' => $kelasList, 'mahasiswa' => null, 'idPrefix' => 'edit-'])

            <div class="mt-6 flex gap-2">
                <button type="submit" class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Simpan Perubahan</button>
                <button type="button" onclick="document.getElementById('dialog-edit-mahasiswa').close()"
                        class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Batal
                </button>
            </div>
        </form>
    </dialog>

    <script>
        function openEditMahasiswaDialog(id, nama, nim, jurusan, kelasId) {
            var dialog = document.getElementById('dialog-edit-mahasiswa');
            var form = dialog.querySelector('form');
            form.action = '/mahasiswa/' + id;
            form.querySelector('[name="_edit_id"]').value = id;
            form.querySelector('[name="nama"]').value = nama;
            form.querySelector('[name="nim"]').value = nim;
            form.querySelector('[name="jurusan"]').value = jurusan;
            form.querySelector('[name="kelas_id"]').value = kelasId;
            dialog.showModal();
        }
    </script>

    @if ($errors->any() && old('nama') !== null && ! old('_edit_id'))
        <script>document.getElementById('dialog-tambah-mahasiswa').showModal();</script>
    @endif

    @if ($errors->any() && old('_edit_id'))
        <script>document.getElementById('dialog-edit-mahasiswa').showModal();</script>
    @endif

    <dialog id="dialog-hapus-semua" class="fixed left-1/2 top-1/2 w-full max-w-md -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-slate-200 bg-white p-6 shadow-lg backdrop:bg-slate-900/60">
        <form method="POST" action="{{ route('mahasiswa.destroyAll') }}">
            @csrf
            @method('DELETE')
            <h3 class="font-serif text-xl font-semibold text-slate-900">Hapus Seluruh Data Mahasiswa</h3>
            <p class="mt-2 text-sm text-slate-600">
                Tindakan ini akan menghapus <strong>{{ $totalMahasiswa }}</strong> data mahasiswa dari semua kelas dan tidak dapat dibatalkan.
                Ketik <strong>HAPUS SEMUA DATA</strong> untuk melanjutkan.
            </p>
            <input type="text" data-confirm-input data-confirm-target="HAPUS SEMUA DATA" autocomplete="off"
                   class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-rose-500 focus:outline-none focus:ring-1 focus:ring-rose-500"
                   placeholder="HAPUS SEMUA DATA">
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('dialog-hapus-semua').close()"
                        class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Batal
                </button>
                <button type="submit" disabled data-confirm-button
                        class="rounded-full bg-rose-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50">
                    Ya, Hapus
                </button>
            </div>
        </form>
    </dialog>

    <script>
        function openConfirmDialog(dialogId) {
            var dialog = document.getElementById(dialogId);
            var input = dialog.querySelector('[data-confirm-input]');
            var button = dialog.querySelector('[data-confirm-button]');
            input.value = '';
            button.disabled = true;
            dialog.showModal();
        }

        document.querySelectorAll('dialog [data-confirm-input]').forEach(function (input) {
            var dialog = input.closest('dialog');
            var button = dialog.querySelector('[data-confirm-button]');
            var target = input.dataset.confirmTarget.toUpperCase();

            input.addEventListener('input', function () {
                button.disabled = input.value.trim().toUpperCase() !== target;
            });
        });
    </script>

    <x-card padded class="mb-5">
        <form method="GET" action="{{ route('mahasiswa.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama / NIM"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">

            <select name="jurusan" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="">Semua Jurusan</option>
                @foreach ($jurusanList as $j)
                    <option value="{{ $j }}" @selected(request('jurusan') === $j)>{{ $j }}</option>
                @endforeach
            </select>

            <select name="kelas_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="">Semua Kelas</option>
                @foreach ($kelasList as $k)
                    <option value="{{ $k->id }}" @selected((string) request('kelas_id') === (string) $k->id)>{{ $k->nama_kelas }} ({{ $k->kategori }})</option>
                @endforeach
            </select>

            <select name="kategori" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                <option value="">Semua Kategori</option>
                <option value="pagi" @selected(request('kategori') === 'pagi')>Pagi</option>
                <option value="malam" @selected(request('kategori') === 'malam')>Malam</option>
            </select>

            <div class="sm:col-span-2 lg:col-span-4 flex gap-2">
                <button type="submit" class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Cari</button>
                <a href="{{ route('mahasiswa.index') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Reset</a>
            </div>
        </form>
    </x-card>

    <form method="POST" action="{{ route('mahasiswa.destroySelected') }}" id="form-hapus-terpilih" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    <x-card>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 lg:px-6">
            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Daftar Mahasiswa</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="btn-toggle-pilih-maba"
                        class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Pilih Mahasiswa
                </button>
                <button type="button" onclick="openConfirmDialog('dialog-hapus-semua')"
                        class="rounded-full border border-rose-300 bg-white px-4 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-100">
                    Hapus Semua Data
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50/70 text-xs uppercase tracking-[0.08em] text-slate-500">
                    <tr>
                        <th class="kolom-pilih-maba hidden w-10 px-4 py-2.5 lg:px-6">
                            <input type="checkbox" id="pilih-semua-maba"
                                   class="rounded border-slate-300 text-gold-600 focus:ring-gold-500">
                        </th>
                        <th class="px-4 py-2.5 lg:px-6">Nama</th>
                        <th class="px-4 py-2.5 lg:px-6">NIM</th>
                        <th class="px-4 py-2.5 lg:px-6">Jurusan</th>
                        <th class="px-4 py-2.5 lg:px-6">Kelas</th>
                        <th class="kolom-aksi-maba hidden px-4 py-2.5 text-right lg:px-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($mahasiswa as $m)
                        <tr class="baris-maba transition-colors hover:bg-slate-50/60">
                            <td class="kolom-pilih-maba hidden px-4 py-3 lg:px-6">
                                <input type="checkbox" name="ids[]" value="{{ $m->id }}" form="form-hapus-terpilih"
                                       class="pilih-maba rounded border-slate-300 text-gold-600 focus:ring-gold-500">
                            </td>
                            <td class="px-4 py-3 font-medium lg:px-6">{{ $m->nama }}</td>
                            <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $m->nim }}</td>
                            <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $m->jurusan }}</td>
                            <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $m->kelas->nama_kelas }}</td>
                            <td class="kolom-aksi-maba hidden px-4 py-3 text-right lg:px-6" data-abaikan-pilih>
                                <button type="button"
                                        onclick="openEditMahasiswaDialog({{ $m->id }}, {{ json_encode($m->nama) }}, {{ json_encode($m->nim) }}, {{ json_encode($m->jurusan) }}, {{ $m->kelas_id }})"
                                        class="mr-3 text-sm font-medium text-slate-700 hover:underline">
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('mahasiswa.destroy', $m) }}" class="inline"
                                      onsubmit="return confirm('Hapus mahasiswa {{ $m->nama }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-rose-600 hover:underline">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">Belum ada data mahasiswa.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div id="bar-aksi-maba" class="hidden items-center justify-between gap-3 border-t border-slate-200 bg-rose-50/50 px-4 py-3 lg:px-6">
            <p class="text-sm text-slate-700"><span id="jumlah-maba-terpilih">0</span> mahasiswa terpilih</p>
            <button type="submit" form="form-hapus-terpilih"
                    onclick="return confirm('Hapus ' + document.getElementById('jumlah-maba-terpilih').textContent + ' mahasiswa terpilih?');"
                    class="rounded-full bg-rose-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-rose-700">
                Hapus Terpilih
            </button>
        </div>

        @if ($filteredByKelas)
            <div class="border-t border-slate-200 px-4 py-3 text-sm text-slate-500 lg:px-6">
                Menampilkan {{ $mahasiswa->count() }} mahasiswa
            </div>
        @else
            <div class="border-t border-slate-200 px-4 py-3 lg:px-6">
                {{ $mahasiswa->links() }}
            </div>
        @endif
    </x-card>

    <script>
        (function () {
            var tombolToggle = document.getElementById('btn-toggle-pilih-maba');
            var kolomPilih = document.querySelectorAll('.kolom-pilih-maba');
            var kolomAksi = document.querySelectorAll('.kolom-aksi-maba');
            var barisMaba = document.querySelectorAll('.baris-maba');
            var checkboxMaba = document.querySelectorAll('.pilih-maba');
            var pilihSemua = document.getElementById('pilih-semua-maba');
            var barAksi = document.getElementById('bar-aksi-maba');
            var jumlahEl = document.getElementById('jumlah-maba-terpilih');
            var modePilih = false;

            function terpilih() {
                return Array.prototype.filter.call(checkboxMaba, function (c) { return c.checked; }).length;
            }

            function perbarui() {
                var n = terpilih();
                jumlahEl.textContent = n;
                barAksi.classList.toggle('hidden', n === 0);
                barAksi.classList.toggle('flex', n > 0);

                barisMaba.forEach(function (baris, i) {
                    baris.classList.toggle('bg-rose-50/60', modePilih && checkboxMaba[i].checked);
                });
            }

            tombolToggle.addEventListener('click', function () {
                modePilih = !modePilih;
                kolomPilih.forEach(function (el) { el.classList.toggle('hidden', !modePilih); });
                kolomAksi.forEach(function (el) { el.classList.toggle('hidden', !modePilih); });
                barisMaba.forEach(function (el) { el.classList.toggle('cursor-pointer', modePilih); });
                tombolToggle.textContent = modePilih ? 'Batal Pilih' : 'Pilih Mahasiswa';

                if (!modePilih) {
                    checkboxMaba.forEach(function (c) { c.checked = false; });
                    pilihSemua.checked = false;
                }
                perbarui();
            });

            pilihSemua.addEventListener('change', function () {
                checkboxMaba.forEach(function (c) { c.checked = pilihSemua.checked; });
                perbarui();
            });

            checkboxMaba.forEach(function (c) {
                c.addEventListener('change', function () {
                    pilihSemua.checked = terpilih() === checkboxMaba.length;
                    perbarui();
                });
            });

            // Seluruh baris jadi area klik supaya gampang dipencet di layar sentuh.
            barisMaba.forEach(function (baris, i) {
                baris.addEventListener('click', function (e) {
                    if (!modePilih) {
                        return;
                    }
                    // Biarkan tombol Edit/Hapus dan checkbox bekerja seperti biasa.
                    if (e.target.closest('[data-abaikan-pilih]') || e.target.classList.contains('pilih-maba')) {
                        return;
                    }

                    checkboxMaba[i].checked = !checkboxMaba[i].checked;
                    pilihSemua.checked = terpilih() === checkboxMaba.length;
                    perbarui();
                });
            });
        })();
    </script>
</x-layouts.app>
