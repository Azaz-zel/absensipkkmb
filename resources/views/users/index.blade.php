<x-layouts.app title="Akun PJ">
    <div class="mb-5 flex justify-end">
        <button type="button" onclick="document.getElementById('dialog-tambah-akun').showModal()"
                class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">
            + Tambah Akun
        </button>
    </div>

    <dialog id="dialog-tambah-akun" class="fixed left-1/2 top-1/2 w-full max-w-2xl -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-slate-200 bg-white p-6 shadow-lg backdrop:bg-slate-900/60">
        <h3 class="mb-4 font-serif text-xl font-semibold text-slate-900">Tambah Akun PJ</h3>
        <form method="POST" action="{{ route('users.store') }}">
            @csrf
            @include('users._form', ['kelasList' => $kelasList, 'idPrefix' => 'create-'])

            <div class="mt-6 flex gap-2">
                <button type="submit" class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Simpan</button>
                <button type="button" onclick="document.getElementById('dialog-tambah-akun').close()"
                        class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Batal
                </button>
            </div>
        </form>
    </dialog>

    <dialog id="dialog-edit-akun" class="fixed left-1/2 top-1/2 w-full max-w-2xl -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-slate-200 bg-white p-6 shadow-lg backdrop:bg-slate-900/60">
        <h3 class="mb-4 font-serif text-xl font-semibold text-slate-900">Edit Akun PJ</h3>
        <form method="POST" action="{{ old('_edit_id') ? route('users.update', old('_edit_id')) : '#' }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="_edit_id" value="{{ old('_edit_id') }}">
            @include('users._form', ['kelasList' => $kelasList, 'isEdit' => true, 'idPrefix' => 'edit-'])

            <div class="mt-6 flex gap-2">
                <button type="submit" class="rounded-full bg-gold-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gold-700">Simpan Perubahan</button>
                <button type="button" onclick="document.getElementById('dialog-edit-akun').close()"
                        class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Batal
                </button>
            </div>
        </form>
    </dialog>

    <script>
        function openEditAkunDialog(id, name, email, username, role, status, kelasIds) {
            var dialog = document.getElementById('dialog-edit-akun');
            var form = dialog.querySelector('form');
            form.action = '/users/' + id;
            form.querySelector('[name="_edit_id"]').value = id;
            form.querySelector('[name="name"]').value = name;
            form.querySelector('[name="email"]').value = email;
            form.querySelector('[name="username"]').value = username;
            form.querySelector('[name="password"]').value = '';
            form.querySelector('[name="role"]').value = role;
            form.querySelector('[name="status"]').value = status;
            form.querySelectorAll('[name="kelas[]"]').forEach(function (cb) {
                cb.checked = kelasIds.includes(parseInt(cb.value, 10));
            });
            dialog.showModal();
        }
    </script>

    @if ($errors->any() && old('name') !== null && ! old('_edit_id'))
        <script>document.getElementById('dialog-tambah-akun').showModal();</script>
    @endif

    @if ($errors->any() && old('_edit_id'))
        <script>document.getElementById('dialog-edit-akun').showModal();</script>
    @endif

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50/70 text-xs uppercase tracking-[0.08em] text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 lg:px-6">Nama</th>
                        <th class="px-4 py-2.5 lg:px-6">Email / Username</th>
                        <th class="px-4 py-2.5 lg:px-6">Role</th>
                        <th class="px-4 py-2.5 lg:px-6">Kelas</th>
                        <th class="px-4 py-2.5 lg:px-6">Status</th>
                        <th class="px-4 py-2.5 text-right lg:px-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr class="transition-colors hover:bg-slate-50/60">
                            <td class="px-4 py-3 font-medium lg:px-6">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-slate-600 lg:px-6">
                                {{ $user->email }}<br>
                                <span class="text-xs text-slate-400">{{ $user->username }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 uppercase text-xs lg:px-6">{{ $user->role }}</td>
                            <td class="px-4 py-3 text-slate-600 lg:px-6">
                                {{ $user->kelas->pluck('nama_kelas')->join(', ') ?: '—' }}
                            </td>
                            <td class="px-4 py-3 lg:px-6"><x-status-badge :status="$user->status" /></td>
                            <td class="px-4 py-3 text-right lg:px-6">
                                <button type="button"
                                        onclick="openEditAkunDialog({{ $user->id }}, {{ json_encode($user->name) }}, {{ json_encode($user->email) }}, {{ json_encode($user->username) }}, {{ json_encode($user->role) }}, {{ json_encode($user->status) }}, {{ json_encode($user->kelas->pluck('id')) }})"
                                        class="mr-3 text-sm font-medium text-slate-700 hover:underline">
                                    Edit
                                </button>

                                <form method="POST" action="{{ route('users.toggleStatus', $user) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="mr-3 text-sm font-medium text-slate-700 hover:underline">
                                        {{ $user->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline"
                                      onsubmit="return confirm('Hapus akun {{ $user->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-rose-600 hover:underline">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">Belum ada akun.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-4 py-3 lg:px-6">
            {{ $users->links() }}
        </div>
    </x-card>
</x-layouts.app>
