@props(['user' => null, 'kelasList', 'assignedKelasIds' => [], 'isEdit' => false, 'idPrefix' => ''])

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label for="{{ $idPrefix }}name" class="mb-1 block text-sm font-medium text-slate-700">Nama</label>
        <input id="{{ $idPrefix }}name" name="name" type="text" value="{{ old('name', $user?->name) }}" required
               class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
    </div>

    <div>
        <label for="{{ $idPrefix }}email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
        <input id="{{ $idPrefix }}email" name="email" type="email" value="{{ old('email', $user?->email) }}" required
               class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
    </div>

    <div>
        <label for="{{ $idPrefix }}username" class="mb-1 block text-sm font-medium text-slate-700">Username</label>
        <input id="{{ $idPrefix }}username" name="username" type="text" value="{{ old('username', $user?->username) }}" required
               class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
    </div>

    <div>
        <label for="{{ $idPrefix }}password" class="mb-1 block text-sm font-medium text-slate-700">
            Password {{ $isEdit ? '(kosongkan jika tidak diubah)' : '' }}
        </label>
        <input id="{{ $idPrefix }}password" name="password" type="password" {{ $isEdit ? '' : 'required' }}
               class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
    </div>

    <div>
        <label for="{{ $idPrefix }}role" class="mb-1 block text-sm font-medium text-slate-700">Role</label>
        <select id="{{ $idPrefix }}role" name="role" required
                class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
            <option value="pj" @selected(old('role', $user?->role ?? 'pj') === 'pj')>PJ Kelas</option>
            <option value="admin" @selected(old('role', $user?->role) === 'admin')>Admin</option>
            <option value="manajer" @selected(old('role', $user?->role) === 'manajer')>Manajer (Sie Kesekre)</option>
        </select>
    </div>

    <div>
        <label for="{{ $idPrefix }}status" class="mb-1 block text-sm font-medium text-slate-700">Status Akun</label>
        <select id="{{ $idPrefix }}status" name="status" required
                class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
            <option value="aktif" @selected(old('status', $user?->status ?? 'aktif') === 'aktif')>Aktif</option>
            <option value="nonaktif" @selected(old('status', $user?->status) === 'nonaktif')>Nonaktif</option>
        </select>
    </div>
</div>

<div class="mt-4">
    <p class="mb-2 text-sm font-medium text-slate-700">Kelas yang Ditugaskan</p>
    <div class="grid max-h-56 grid-cols-4 gap-2 overflow-y-auto rounded-lg border border-slate-200 p-3 sm:grid-cols-6 lg:grid-cols-8">
        @foreach ($kelasList as $k)
            @php $checked = in_array($k->id, old('kelas', $assignedKelasIds), true); @endphp
            <label class="flex items-center gap-1.5 text-sm">
                <input type="checkbox" name="kelas[]" value="{{ $k->id }}" @checked($checked)
                       class="rounded border-slate-300 text-gold-600 focus:ring-gold-500">
                {{ $k->nama_kelas }}
            </label>
        @endforeach
    </div>
</div>
