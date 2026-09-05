@php
    $countSemua = $kelasList->count();
@endphp

<x-layouts.app :title="$sesi->nama_sesi">
    <div class="-mt-2 mb-5 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('sesi.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
                </svg>
                Kembali
            </a>
            <span class="text-slate-300">|</span>
            <p class="text-sm text-slate-500">{{ $sesi->tanggal->translatedFormat('d M Y') }}</p>
        </div>

        <form method="POST" action="{{ route('sesi.destroy', $sesi) }}"
              onsubmit="return confirm('Hapus sesi {{ $sesi->nama_sesi }}? Seluruh data absensi pada sesi ini juga akan terhapus.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm font-medium text-rose-600 hover:underline">Hapus Sesi Ini</button>
        </form>
    </div>

    <form method="POST" action="{{ route('sesi.bulk', $sesi) }}" id="form-sesi-massal">
        @csrf
        <input type="hidden" name="scope" value="terpilih">
        <input type="hidden" name="action" id="input-aksi-sesi" value="">

        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 lg:px-6">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Daftar Kelas</p>

                <div class="flex flex-wrap items-center gap-2">
                    <div id="pilih-cepat" class="hidden flex-wrap items-center gap-2">
                        <span class="text-xs font-medium text-slate-500">Pilih cepat:</span>
                        <button type="button" data-pilih-kategori="pagi"
                                class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800 transition hover:bg-amber-100">
                            Kelas Pagi ({{ $kelasList->where('kategori', 'pagi')->count() }})
                        </button>
                        <button type="button" data-pilih-kategori="malam"
                                class="rounded-full border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-800 transition hover:bg-indigo-100">
                            Kelas Malam ({{ $kelasList->where('kategori', 'malam')->count() }})
                        </button>
                        <button type="button" data-pilih-kategori="semua"
                                class="rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                            Semua
                        </button>
                        <button type="button" data-pilih-kategori="kosong"
                                class="rounded-full px-3 py-1.5 text-xs font-medium text-slate-500 transition hover:text-slate-800 hover:underline">
                            Bersihkan
                        </button>
                    </div>

                    <button type="button" id="btn-toggle-pilih"
                            class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Pilih Kelas
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50/70 text-xs uppercase tracking-[0.08em] text-slate-500">
                        <tr>
                            <th class="kolom-pilih hidden w-10 px-4 py-2.5 lg:px-6">
                                <input type="checkbox" id="pilih-semua-kelas"
                                       class="rounded border-slate-300 text-gold-600 focus:ring-gold-500">
                            </th>
                            <th class="px-4 py-2.5 lg:px-6">Kelas</th>
                            <th class="px-4 py-2.5 lg:px-6">Kategori</th>
                            <th class="px-4 py-2.5 lg:px-6">Jumlah Maba</th>
                            <th class="px-4 py-2.5 lg:px-6">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($kelasList as $kelas)
                            @php $status = $kelas->sesiKelas->first()->status ?? 'belum_dibuka'; @endphp
                            <tr class="baris-kelas transition-colors hover:bg-slate-50/60">
                                <td class="kolom-pilih hidden px-4 py-3 lg:px-6">
                                    <input type="checkbox" name="kelas_ids[]" value="{{ $kelas->id }}"
                                           data-kategori="{{ $kelas->kategori }}"
                                           class="pilih-kelas rounded border-slate-300 text-gold-600 focus:ring-gold-500">
                                </td>
                                <td class="px-4 py-3 font-medium lg:px-6">{{ $kelas->nama_kelas }}</td>
                                <td class="px-4 py-3 text-slate-600 capitalize lg:px-6">{{ $kelas->kategori }}</td>
                                <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $kelas->mahasiswa_count }}</td>
                                <td class="px-4 py-3 lg:px-6"><x-status-badge :status="$status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div id="bar-aksi-sesi" class="hidden items-center justify-between gap-3 border-t border-slate-200 bg-gold-50/50 px-4 py-3 lg:px-6">
                <p class="text-sm text-slate-700"><span id="jumlah-terpilih">0</span> kelas terpilih</p>
                <button type="button" onclick="document.getElementById('dialog-aksi-sesi').showModal()"
                        class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">
                    Lanjutkan
                </button>
            </div>
        </x-card>

        <dialog id="dialog-aksi-sesi" class="fixed left-1/2 top-1/2 w-full max-w-md -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-slate-200 bg-white p-6 shadow-lg backdrop:bg-slate-900/60">
            <h3 class="font-serif text-xl font-semibold text-slate-900">Aksi untuk Kelas Terpilih</h3>
            <p class="mt-2 text-sm text-slate-600">
                <span id="jumlah-terpilih-dialog">0</span> kelas dipilih. Mau dibuka atau ditutup absensinya?
            </p>
            <p class="mt-2 text-xs text-slate-500">
                Menutup kelas otomatis menandai maba yang belum diabsen sebagai <strong>alpa</strong>.
            </p>

            <div class="mt-5 flex flex-col gap-2">
                <button type="submit" onclick="document.getElementById('input-aksi-sesi').value='buka'"
                        class="w-full rounded-full bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                    Buka Absensi
                </button>
                <button type="submit" onclick="document.getElementById('input-aksi-sesi').value='tutup'"
                        class="w-full rounded-full bg-rose-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-rose-700">
                    Tutup Absensi
                </button>
                <button type="button" onclick="document.getElementById('dialog-aksi-sesi').close()"
                        class="w-full rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Batal
                </button>
            </div>
        </dialog>
    </form>

    <script>
        (function () {
            var tombolToggle = document.getElementById('btn-toggle-pilih');
            var kolomPilih = document.querySelectorAll('.kolom-pilih');
            var barisKelas = document.querySelectorAll('.baris-kelas');
            var checkboxKelas = document.querySelectorAll('.pilih-kelas');
            var pilihSemua = document.getElementById('pilih-semua-kelas');
            var pilihCepat = document.getElementById('pilih-cepat');
            var barAksi = document.getElementById('bar-aksi-sesi');
            var jumlahEl = document.getElementById('jumlah-terpilih');
            var jumlahDialogEl = document.getElementById('jumlah-terpilih-dialog');
            var modePilih = false;

            function terpilih() {
                return Array.prototype.filter.call(checkboxKelas, function (c) { return c.checked; }).length;
            }

            function perbarui() {
                var n = terpilih();
                jumlahEl.textContent = n;
                jumlahDialogEl.textContent = n;
                barAksi.classList.toggle('hidden', n === 0);
                barAksi.classList.toggle('flex', n > 0);

                barisKelas.forEach(function (baris, i) {
                    baris.classList.toggle('bg-gold-50/60', modePilih && checkboxKelas[i].checked);
                });
            }

            tombolToggle.addEventListener('click', function () {
                modePilih = !modePilih;
                kolomPilih.forEach(function (el) { el.classList.toggle('hidden', !modePilih); });
                barisKelas.forEach(function (el) { el.classList.toggle('cursor-pointer', modePilih); });
                pilihCepat.classList.toggle('hidden', !modePilih);
                pilihCepat.classList.toggle('flex', modePilih);
                tombolToggle.textContent = modePilih ? 'Batal Pilih' : 'Pilih Kelas';

                if (!modePilih) {
                    checkboxKelas.forEach(function (c) { c.checked = false; });
                    pilihSemua.checked = false;
                }
                perbarui();
            });

            pilihSemua.addEventListener('change', function () {
                checkboxKelas.forEach(function (c) { c.checked = pilihSemua.checked; });
                perbarui();
            });

            checkboxKelas.forEach(function (c) {
                c.addEventListener('change', function () {
                    pilihSemua.checked = terpilih() === checkboxKelas.length;
                    perbarui();
                });
            });

            // Pilih cepat per kategori: cukup sekali pencet untuk seluruh kelas pagi/malam.
            pilihCepat.querySelectorAll('[data-pilih-kategori]').forEach(function (tombol) {
                tombol.addEventListener('click', function () {
                    var target = tombol.dataset.pilihKategori;

                    checkboxKelas.forEach(function (c) {
                        c.checked = target === 'semua'
                            ? true
                            : (target === 'kosong' ? false : c.dataset.kategori === target);
                    });

                    pilihSemua.checked = terpilih() === checkboxKelas.length;
                    perbarui();
                });
            });

            // Seluruh baris jadi area klik supaya gampang dipencet di layar sentuh.
            barisKelas.forEach(function (baris, i) {
                baris.addEventListener('click', function (e) {
                    if (!modePilih || e.target.classList.contains('pilih-kelas')) {
                        return;
                    }

                    checkboxKelas[i].checked = !checkboxKelas[i].checked;
                    pilihSemua.checked = terpilih() === checkboxKelas.length;
                    perbarui();
                });
            });
        })();
    </script>
</x-layouts.app>
