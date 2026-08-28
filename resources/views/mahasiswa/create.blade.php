<x-layouts.app title="Tambah Mahasiswa">
    <x-card padded class="max-w-2xl">
        <form method="POST" action="{{ route('mahasiswa.store') }}">
            @csrf
            @include('mahasiswa._form', ['kelasList' => $kelasList])

            <div class="mt-6 flex gap-2">
                <button type="submit" class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Simpan</button>
                <a href="{{ route('mahasiswa.index') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Batal</a>
            </div>
        </form>
    </x-card>
</x-layouts.app>
