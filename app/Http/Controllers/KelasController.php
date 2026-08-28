<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\SesiAbsensi;
use App\Models\SesiKelas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KelasController extends Controller
{
    public function index(): View
    {
        $kelasList = Kelas::withCount('mahasiswa')
            ->orderByRaw("CASE WHEN kategori = 'pagi' THEN 0 ELSE 1 END")
            ->orderBy('nama_kelas')
            ->get();

        return view('kelas.index', compact('kelasList'));
    }

    public function create(): View
    {
        return view('kelas.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $kelas = Kelas::create($data);

        // Keep every existing sesi in sync so this kelas can immediately be opened/closed there too.
        $now = now();
        $rows = SesiAbsensi::pluck('id')->map(fn ($sesiId) => [
            'sesi_absensi_id' => $sesiId,
            'kelas_id' => $kelas->id,
            'status' => 'belum_dibuka',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows !== []) {
            SesiKelas::insert($rows);
        }

        return redirect()->route('kelas.index')->with('success', "Kelas {$kelas->nama_kelas} berhasil ditambahkan.");
    }

    public function edit(Kelas $kelas): View
    {
        return view('kelas.edit', compact('kelas'));
    }

    public function update(Request $request, Kelas $kelas): RedirectResponse
    {
        $data = $this->validated($request, $kelas->id);

        $kelas->update($data);

        return redirect()->route('kelas.index')->with('success', "Kelas {$kelas->nama_kelas} berhasil diperbarui.");
    }

    public function destroy(Kelas $kelas): RedirectResponse
    {
        $mahasiswaCount = Mahasiswa::withTrashed()->where('kelas_id', $kelas->id)->count();

        if ($mahasiswaCount > 0) {
            return back()->with('error', "Kelas {$kelas->nama_kelas} tidak dapat dihapus karena masih memiliki {$mahasiswaCount} data mahasiswa (termasuk yang sudah dihapus). Pindahkan atau hapus permanen data mahasiswa tersebut terlebih dahulu.");
        }

        $kelas->delete();

        return redirect()->route('kelas.index')->with('success', "Kelas {$kelas->nama_kelas} berhasil dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->merge(['nama_kelas' => strtoupper((string) $request->input('nama_kelas'))]);

        return $request->validate([
            'nama_kelas' => [
                'required', 'string', 'max:10',
                function ($attribute, $value, $fail) use ($request) {
                    $kategori = $request->input('kategori');

                    if ($kategori === 'pagi' && ! preg_match('/^[A-Z]$/', $value)) {
                        $fail('Nama kelas pagi harus berupa 1 huruf (A-Z).');
                    }

                    if ($kategori === 'malam' && ! preg_match('/^[A-Z]{2}$/', $value)) {
                        $fail('Nama kelas malam harus berupa 2 huruf (AA-ZZ).');
                    }
                },
                Rule::unique('kelas', 'nama_kelas')->ignore($ignoreId),
            ],
            'kategori' => ['required', Rule::in(['pagi', 'malam'])],
            'kapasitas' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
