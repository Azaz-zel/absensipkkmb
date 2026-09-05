<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\Pelanggaran;
use App\Models\SesiAbsensi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PoinController extends Controller
{
    public function index(Request $request): View
    {
        return $request->user()->isManajer()
            ? $this->indexForManajer($request)
            : $this->indexForAdmin($request);
    }

    private function indexForAdmin(Request $request): View
    {
        $sesiId = $request->integer('sesi') ?: null;
        $sort = $request->string('sort', 'nama')->toString();

        $query = Mahasiswa::query()
            ->with('kelas')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q');
                $q->where(fn ($q) => $q->where('nama', 'like', "%{$term}%")->orWhere('nim', 'like', "%{$term}%"));
            })
            ->when($request->filled('kelas_id'), fn ($q) => $q->where('kelas_id', $request->integer('kelas_id')))
            ->withCount($this->absensiCounts($sesiId))
            ->withSum('pelanggaran', 'poin');

        match ($sort) {
            'poin_desc' => $query->orderByRaw($this->totalPoinExpression().' DESC'),
            'poin_asc' => $query->orderByRaw($this->totalPoinExpression().' ASC'),
            default => $query->orderBy('nama'),
        };

        $filteredByKelas = $request->filled('kelas_id');
        $mahasiswa = $filteredByKelas ? $query->get() : $query->paginate(40)->withQueryString();

        // pluck() jalan baik di paginator maupun collection biasa.
        $riwayat = Pelanggaran::whereIn('mahasiswa_id', $mahasiswa->pluck('id'))
            ->with('pencatat')
            ->latest()
            ->get()
            ->groupBy('mahasiswa_id');

        return view('poin.index', [
            'mahasiswa' => $mahasiswa,
            'riwayat' => $riwayat,
            'kelasList' => Kelas::orderByRaw("CASE WHEN kategori = 'pagi' THEN 0 ELSE 1 END")->orderBy('nama_kelas')->get(),
            'sesiList' => SesiAbsensi::orderByDesc('tanggal')->get(),
            'filteredByKelas' => $filteredByKelas,
            'sort' => $sort,
        ]);
    }

    private function indexForManajer(Request $request): View
    {
        $term = $request->string('q')->toString();
        $mahasiswa = collect();

        if ($term !== '') {
            $mahasiswa = Mahasiswa::query()
                ->with('kelas')
                ->where(fn ($q) => $q->where('nim', 'like', "%{$term}%")->orWhere('nama', 'like', "%{$term}%"))
                ->withCount($this->absensiCounts(null))
                ->withSum('pelanggaran', 'poin')
                ->orderBy('nama')
                ->limit(25)
                ->get();
        }

        return view('poin.manajer', [
            'mahasiswa' => $mahasiswa,
            'term' => $term,
        ]);
    }

    public function store(Request $request, Mahasiswa $mahasiswa): RedirectResponse
    {
        $data = $request->validate([
            'jenis' => ['required', 'string', 'max:255'],
            'poin' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $mahasiswa->pelanggaran()->create([
            ...$data,
            'dicatat_oleh' => $request->user()->id,
        ]);

        return back()->with('success', "Pelanggaran {$mahasiswa->nama} berhasil dicatat (+{$data['poin']} poin).");
    }

    public function destroy(Pelanggaran $pelanggaran): RedirectResponse
    {
        $pelanggaran->delete();

        return back()->with('success', 'Catatan pelanggaran berhasil dihapus.');
    }

    /** Hitungan absensi per status, opsional dibatasi satu sesi. */
    private function absensiCounts(?int $sesiId): array
    {
        return [
            'absensi as hadir_count' => fn ($q) => $q->where('status', 'hadir')->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId)),
            'absensi as izin_count' => fn ($q) => $q->where('status', 'izin')->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId)),
            'absensi as sakit_count' => fn ($q) => $q->where('status', 'sakit')->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId)),
            'absensi as alpa_count' => fn ($q) => $q->where('status', 'alpa')->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId)),
        ];
    }

    /** Total poin = poin absensi + poin pelanggaran, buat dipakai di ORDER BY. */
    private function totalPoinExpression(): string
    {
        return '(alpa_count * 5 + (sakit_count + izin_count) * 3 + COALESCE(pelanggaran_sum_poin, 0))';
    }
}
