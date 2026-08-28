<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\SesiAbsensi;
use App\Models\SesiKelas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SesiAbsensiController extends Controller
{
    public function index(): View
    {
        $sesiList = SesiAbsensi::withCount([
            'sesiKelas as dibuka_count' => fn ($q) => $q->where('status', 'dibuka'),
            'sesiKelas as ditutup_count' => fn ($q) => $q->where('status', 'ditutup'),
            'sesiKelas as belum_dibuka_count' => fn ($q) => $q->where('status', 'belum_dibuka'),
        ])->orderByDesc('tanggal')->paginate(20);

        return view('sesi.index', compact('sesiList'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_sesi' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
        ]);

        $sesi = SesiAbsensi::create([...$data, 'created_by' => $request->user()->id]);

        $now = now();
        $rows = Kelas::pluck('id')->map(fn ($kelasId) => [
            'sesi_absensi_id' => $sesi->id,
            'kelas_id' => $kelasId,
            'status' => 'belum_dibuka',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        SesiKelas::insert($rows);

        return redirect()->route('sesi.show', $sesi)->with('success', 'Sesi absensi berhasil dibuat.');
    }

    public function show(SesiAbsensi $sesi): View
    {
        $kelasList = Kelas::withCount('mahasiswa')
            ->with(['sesiKelas' => fn ($q) => $q->where('sesi_absensi_id', $sesi->id)])
            ->orderByRaw("CASE WHEN kategori = 'pagi' THEN 0 ELSE 1 END")->orderBy('nama_kelas')
            ->get();

        return view('sesi.show', compact('sesi', 'kelasList'));
    }

    public function destroy(SesiAbsensi $sesi): RedirectResponse
    {
        $sesi->delete();

        return redirect()->route('sesi.index')->with('success', "Sesi {$sesi->nama_sesi} berhasil dihapus.");
    }

    public function bulk(Request $request, SesiAbsensi $sesi): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['buka', 'tutup'])],
            'scope' => ['required', Rule::in(['pagi', 'malam', 'semua'])],
        ]);

        $query = SesiKelas::where('sesi_absensi_id', $sesi->id);

        if ($data['scope'] !== 'semua') {
            $query->whereHas('kelas', fn ($q) => $q->where('kategori', $data['scope']));
        }

        $this->applyStatus($query, $data['action'], $request->user()->id);

        return redirect()->route('sesi.show', $sesi)->with('success', 'Status sesi berhasil diperbarui secara massal.');
    }

    public function openClass(Request $request, SesiAbsensi $sesi, Kelas $kelas): RedirectResponse
    {
        $this->applyStatus($this->sesiKelasQuery($sesi, $kelas), 'buka', $request->user()->id);

        return redirect()->route('sesi.show', $sesi)->with('success', "Absensi kelas {$kelas->nama_kelas} dibuka.");
    }

    public function closeClass(Request $request, SesiAbsensi $sesi, Kelas $kelas): RedirectResponse
    {
        $this->applyStatus($this->sesiKelasQuery($sesi, $kelas), 'tutup', $request->user()->id);

        return redirect()->route('sesi.show', $sesi)->with('success', "Absensi kelas {$kelas->nama_kelas} ditutup.");
    }

    public function reopen(Request $request, SesiAbsensi $sesi, Kelas $kelas): RedirectResponse
    {
        $this->applyStatus($this->sesiKelasQuery($sesi, $kelas), 'buka', $request->user()->id);

        return redirect()->route('sesi.show', $sesi)->with('success', "Absensi kelas {$kelas->nama_kelas} dibuka kembali.");
    }

    private function sesiKelasQuery(SesiAbsensi $sesi, Kelas $kelas)
    {
        return SesiKelas::where('sesi_absensi_id', $sesi->id)->where('kelas_id', $kelas->id);
    }

    private function applyStatus($query, string $action, int $userId): void
    {
        $status = $action === 'buka' ? 'dibuka' : 'ditutup';
        $extra = $action === 'buka'
            ? ['opened_at' => now(), 'opened_by' => $userId]
            : ['closed_at' => now(), 'closed_by' => $userId];

        $query->update(['status' => $status, ...$extra]);
    }
}
