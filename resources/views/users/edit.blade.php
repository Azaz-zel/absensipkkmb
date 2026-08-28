<x-layouts.app title="Edit Akun PJ">
    <x-card padded class="max-w-3xl">
        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf
            @method('PUT')
            @include('users._form', ['user' => $user, 'kelasList' => $kelasList, 'assignedKelasIds' => $assignedKelasIds, 'isEdit' => true])

            <div class="mt-6 flex gap-2">
                <button type="submit" class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Simpan Perubahan</button>
                <a href="{{ route('users.index') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Batal</a>
            </div>
        </form>
    </x-card>
</x-layouts.app>
