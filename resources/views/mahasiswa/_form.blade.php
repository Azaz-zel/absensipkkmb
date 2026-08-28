@props(['mahasiswa' => null, 'kelasList', 'idPrefix' => ''])

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label for="{{ $idPrefix }}nama" class="mb-1 block text-sm font-medium text-slate-700">Nama</label>
        <input id="{{ $idPrefix }}nama" name="nama" type="text" value="{{ old('nama', $mahasiswa?->nama) }}" required
               class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
    </div>

    <div>
        <label for="{{ $idPrefix }}nim" class="mb-1 block text-sm font-medium text-slate-700">NIM</label>
        <input id="{{ $idPrefix }}nim" name="nim" type="text" value="{{ old('nim', $mahasiswa?->nim) }}" required
               class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
    </div>

    <div>
        <label for="{{ $idPrefix }}jurusan" class="mb-1 block text-sm font-medium text-slate-700">Jurusan</label>
        <input id="{{ $idPrefix }}jurusan" name="jurusan" type="text" value="{{ old('jurusan', $mahasiswa?->jurusan) }}" required
               class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
    </div>

    <div>
        <label for="{{ $idPrefix }}kelas_id" class="mb-1 block text-sm font-medium text-slate-700">Kelas</label>
        <select id="{{ $idPrefix }}kelas_id" name="kelas_id" required
                class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
            <option value="">Pilih kelas</option>
            @foreach ($kelasList as $k)
                <option value="{{ $k->id }}" @selected((int) old('kelas_id', $mahasiswa?->kelas_id) === $k->id)>
                    {{ $k->nama_kelas }} ({{ $k->kategori }})
                </option>
            @endforeach
        </select>
    </div>
</div>
