<x-layouts.app title="Kelola Kelas">
    <div class="mb-5 flex justify-end">
        <button type="button" onclick="document.getElementById('dialog-tambah-kelas').showModal()"
                class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">
            + Tambah Kelas
        </button>
    </div>

    <dialog id="dialog-tambah-kelas" class="fixed left-1/2 top-1/2 w-full max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-slate-200 bg-white p-6 shadow-lg backdrop:bg-slate-900/60">
        <h3 class="mb-4 font-serif text-xl font-semibold text-slate-900">Tambah Kelas</h3>
        <form method="POST" action="{{ route('kelas.store') }}">
            @csrf
            @include('kelas._form', ['idPrefix' => 'create-'])

            <div class="mt-6 flex gap-2">
                <button type="submit" class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Simpan</button>
                <button type="button" onclick="document.getElementById('dialog-tambah-kelas').close()"
                        class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Batal
                </button>
            </div>
        </form>
    </dialog>

    <dialog id="dialog-edit-kelas" class="fixed left-1/2 top-1/2 w-full max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-slate-200 bg-white p-6 shadow-lg backdrop:bg-slate-900/60">
        <h3 class="mb-4 font-serif text-xl font-semibold text-slate-900">Edit Kelas</h3>
        <form method="POST" action="{{ old('_edit_id') ? route('kelas.update', old('_edit_id')) : '#' }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="_edit_id" value="{{ old('_edit_id') }}">
            @include('kelas._form', ['kelas' => null, 'idPrefix' => 'edit-'])

            <div class="mt-6 flex gap-2">
                <button type="submit" class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Simpan Perubahan</button>
                <button type="button" onclick="document.getElementById('dialog-edit-kelas').close()"
                        class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Batal
                </button>
            </div>
        </form>
    </dialog>

    <script>
        function openEditKelasDialog(id, namaKelas, kategori, kapasitas) {
            var dialog = document.getElementById('dialog-edit-kelas');
            var form = dialog.querySelector('form');
            form.action = '/kelas/' + id;
            form.querySelector('[name="_edit_id"]').value = id;
            form.querySelector('[name="nama_kelas"]').value = namaKelas;
            form.querySelector('[name="kategori"]').value = kategori;
            form.querySelector('[name="kapasitas"]').value = kapasitas ?? '';
            dialog.showModal();
        }
    </script>

    @if ($errors->any() && old('nama_kelas') !== null && ! old('_edit_id'))
        <script>document.getElementById('dialog-tambah-kelas').showModal();</script>
    @endif

    @if ($errors->any() && old('_edit_id'))
        <script>document.getElementById('dialog-edit-kelas').showModal();</script>
    @endif

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50/70 text-xs uppercase tracking-[0.08em] text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 lg:px-6">Kelas</th>
                        <th class="px-4 py-2.5 lg:px-6">Kategori</th>
                        <th class="px-4 py-2.5 lg:px-6">Kapasitas</th>
                        <th class="px-4 py-2.5 lg:px-6">Jumlah Maba</th>
                        <th class="px-4 py-2.5 lg:px-6">Status</th>
                        <th class="px-4 py-2.5 text-right lg:px-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($kelasList as $kelas)
                        <tr class="transition-colors hover:bg-slate-50/60">
                            <td class="px-4 py-3 font-medium lg:px-6">{{ $kelas->nama_kelas }}</td>
                            <td class="px-4 py-3 text-slate-600 capitalize lg:px-6">{{ $kelas->kategori }}</td>
                            <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $kelas->kapasitas ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600 lg:px-6">{{ $kelas->mahasiswa_count }}</td>
                            <td class="px-4 py-3 lg:px-6"><x-status-badge :status="$kelas->status" /></td>
                            <td class="px-4 py-3 text-right lg:px-6">
                                <button type="button"
                                        onclick="openEditKelasDialog({{ $kelas->id }}, {{ json_encode($kelas->nama_kelas) }}, {{ json_encode($kelas->kategori) }}, {{ json_encode($kelas->kapasitas) }})"
                                        class="mr-3 text-sm font-medium text-slate-700 hover:underline">
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('kelas.destroy', $kelas) }}" class="inline"
                                      onsubmit="return confirm('Hapus kelas {{ $kelas->nama_kelas }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-rose-600 hover:underline">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">Belum ada kelas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-4 py-3 text-sm text-slate-500 lg:px-6">
            Menampilkan {{ $kelasList->count() }} kelas
        </div>
    </x-card>
</x-layouts.app>
