<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->with('kelas')->orderBy('role')->orderBy('name')->paginate(20);
        $kelasList = Kelas::orderByRaw("CASE WHEN kategori = 'pagi' THEN 0 ELSE 1 END")->orderBy('nama_kelas')->get();

        return view('users.index', compact('users', 'kelasList'));
    }

    public function create(): View
    {
        $kelasList = Kelas::orderByRaw("CASE WHEN kategori = 'pagi' THEN 0 ELSE 1 END")->orderBy('nama_kelas')->get();

        return view('users.create', compact('kelasList'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'password' => $data['password'],
            'role' => $data['role'],
            'status' => $data['status'],
        ]);

        $user->kelas()->sync($request->input('kelas', []));

        return redirect()->route('users.index')->with('success', 'Akun berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        $kelasList = Kelas::orderByRaw("CASE WHEN kategori = 'pagi' THEN 0 ELSE 1 END")->orderBy('nama_kelas')->get();
        $assignedKelasIds = $user->kelas()->pluck('kelas.id')->all();

        return view('users.edit', compact('user', 'kelasList', 'assignedKelasIds'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate($this->rules($user->id));

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'role' => $data['role'],
            'status' => $data['status'],
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
        $user->kelas()->sync($request->input('kelas', []));

        return redirect()->route('users.index')->with('success', 'Akun berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, 'Tidak dapat menghapus akun sendiri.');

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Akun berhasil dihapus.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, 'Tidak dapat menonaktifkan akun sendiri.');

        $user->update(['status' => $user->status === 'aktif' ? 'nonaktif' : 'aktif']);

        return redirect()->route('users.index')->with('success', 'Status akun berhasil diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($ignoreId)],
            'password' => [$ignoreId ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,pj,manajer'],
            'status' => ['required', 'in:aktif,nonaktif'],
            'kelas' => ['array'],
            'kelas.*' => ['integer', 'exists:kelas,id'],
        ];
    }
}
