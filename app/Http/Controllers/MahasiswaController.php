<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Mahasiswa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MahasiswaController extends Controller
{
    public function index(Request $request): View
    {
        $query = Mahasiswa::query()
            ->with('kelas')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q');
                $q->where(fn ($q) => $q->where('nama', 'like', "%{$term}%")->orWhere('nim', 'like', "%{$term}%"));
            })
            ->when($request->filled('jurusan'), fn ($q) => $q->where('jurusan', $request->string('jurusan')))
            ->when($request->filled('kelas_id'), fn ($q) => $q->where('kelas_id', $request->integer('kelas_id')))
            ->when($request->filled('kategori'), fn ($q) => $q->whereHas('kelas', fn ($q) => $q->where('kategori', $request->string('kategori'))))
            ->orderBy('nama');

        $filteredByKelas = $request->filled('kelas_id');
        $selectedKelas = $filteredByKelas ? Kelas::find($request->integer('kelas_id')) : null;

        $mahasiswa = $filteredByKelas ? $query->get() : $query->paginate(40)->withQueryString();

        $kelasList = Kelas::orderByRaw("CASE WHEN kategori = 'pagi' THEN 0 ELSE 1 END")->orderBy('nama_kelas')->get();
        $jurusanList = Mahasiswa::query()->select('jurusan')->distinct()->orderBy('jurusan')->pluck('jurusan');
        $totalMahasiswa = Mahasiswa::count();

        return view('mahasiswa.index', compact('mahasiswa', 'kelasList', 'jurusanList', 'filteredByKelas', 'selectedKelas', 'totalMahasiswa'));
    }

    public function create(): View
    {
        $kelasList = Kelas::orderByRaw("CASE WHEN kategori = 'pagi' THEN 0 ELSE 1 END")->orderBy('nama_kelas')->get();

        return view('mahasiswa.create', compact('kelasList'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        Mahasiswa::create($data);

        return redirect()->route('mahasiswa.index')->with('success', 'Mahasiswa berhasil ditambahkan.');
    }

    public function edit(Mahasiswa $mahasiswa): View
    {
        $kelasList = Kelas::orderByRaw("CASE WHEN kategori = 'pagi' THEN 0 ELSE 1 END")->orderBy('nama_kelas')->get();

        return view('mahasiswa.edit', compact('mahasiswa', 'kelasList'));
    }

    public function update(Request $request, Mahasiswa $mahasiswa): RedirectResponse
    {
        $data = $request->validate($this->rules($mahasiswa->id));

        $mahasiswa->update($data);

        return redirect()->route('mahasiswa.index')->with('success', 'Data mahasiswa berhasil diperbarui.');
    }

    public function destroy(Mahasiswa $mahasiswa): RedirectResponse
    {
        $mahasiswa->delete();

        return redirect()->route('mahasiswa.index')->with('success', 'Mahasiswa berhasil dihapus.');
    }

    public function destroySelected(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:mahasiswa,id'],
        ]);

        $count = Mahasiswa::whereIn('id', $data['ids'])->count();

        Mahasiswa::whereIn('id', $data['ids'])->delete();

        return redirect()->route('mahasiswa.index')
            ->with('success', "{$count} data mahasiswa terpilih berhasil dihapus.");
    }

    public function destroyByKelas(Kelas $kelas): RedirectResponse
    {
        $count = Mahasiswa::where('kelas_id', $kelas->id)->count();

        Mahasiswa::where('kelas_id', $kelas->id)->delete();

        return redirect()->route('mahasiswa.index')
            ->with('success', "{$count} data mahasiswa kelas {$kelas->nama_kelas} berhasil dihapus.");
    }

    public function destroyAll(): RedirectResponse
    {
        $count = Mahasiswa::count();

        Mahasiswa::query()->delete();

        return redirect()->route('mahasiswa.index')
            ->with('success', "Seluruh data mahasiswa ({$count} data) berhasil dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'nim' => [
                'required', 'string', 'max:50',
                Rule::unique('mahasiswa', 'nim')->whereNull('deleted_at')->ignore($ignoreId),
            ],
            'jurusan' => ['required', 'string', 'max:255'],
            'kelas_id' => ['required', 'exists:kelas,id'],
        ];
    }
}
