@props(['kelas' => null, 'idPrefix' => ''])

<div>
    <label for="{{ $idPrefix }}nama_kelas" class="mb-1 block text-sm font-medium text-slate-700">Nama Kelas</label>
    <input id="{{ $idPrefix }}nama_kelas" name="nama_kelas" type="text" value="{{ old('nama_kelas', $kelas?->nama_kelas) }}" required
           maxlength="10" style="text-transform: uppercase"
           class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500"
           placeholder="Contoh: A untuk pagi, AJ untuk malam">
    <p class="mt-1 text-xs text-slate-500">Kelas pagi: 1 huruf (A–Z). Kelas malam: 2 huruf (AA–ZZ).</p>
</div>

<div class="mt-4">
    <label for="{{ $idPrefix }}kategori" class="mb-1 block text-sm font-medium text-slate-700">Kategori</label>
    <select id="{{ $idPrefix }}kategori" name="kategori" required
            class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
        <option value="pagi" @selected(old('kategori', $kelas?->kategori ?? 'pagi') === 'pagi')>Pagi</option>
        <option value="malam" @selected(old('kategori', $kelas?->kategori) === 'malam')>Malam</option>
    </select>
</div>

<div class="mt-4">
    <label for="{{ $idPrefix }}kapasitas" class="mb-1 block text-sm font-medium text-slate-700">Kapasitas (opsional)</label>
    <input id="{{ $idPrefix }}kapasitas" name="kapasitas" type="number" min="1" value="{{ old('kapasitas', $kelas?->kapasitas) }}"
           class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
</div>
