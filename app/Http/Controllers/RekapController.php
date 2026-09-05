<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\SesiAbsensi;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RekapController extends Controller
{
    public function perMahasiswa(Request $request): View
    {
        $user = $request->user();

        $sesiId = $request->integer('sesi') ?: null;
        $kehadiran = $request->string('kehadiran')->toString();

        $applyFilters = function ($q) use ($user, $request, $sesiId, $kehadiran) {
            return $q
                ->when(! $user->isAdmin(), fn ($q) => $q->whereIn('kelas_id', $user->kelas()->pluck('kelas.id')))
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = $request->string('q');
                    $q->where(fn ($q) => $q->where('nama', 'like', "%{$term}%")->orWhere('nim', 'like', "%{$term}%"));
                })
                ->when($request->filled('jurusan'), fn ($q) => $q->where('jurusan', $request->string('jurusan')))
                ->when($request->filled('kelas_id'), fn ($q) => $q->where('kelas_id', $request->integer('kelas_id')))
                ->when($request->filled('kategori'), fn ($q) => $q->whereHas('kelas', fn ($q) => $q->where('kategori', $request->string('kategori'))))
                ->when($kehadiran !== '', function ($q) use ($sesiId, $kehadiran) {
                    $q->whereHas('absensi', function ($q) use ($sesiId, $kehadiran) {
                        $q->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId));
                        $kehadiran === 'hadir'
                            ? $q->where('status', 'hadir')
                            : $q->whereIn('status', ['izin', 'sakit', 'alpa']);
                    });
                });
        };

        $sort = $request->string('sort', 'nama')->toString();

        $query = $applyFilters(Mahasiswa::query())
            ->with('kelas')
            ->withCount([
                'absensi as hadir_count' => fn ($q) => $q->where('status', 'hadir')->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId)),
                'absensi as izin_count' => fn ($q) => $q->where('status', 'izin')->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId)),
                'absensi as sakit_count' => fn ($q) => $q->where('status', 'sakit')->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId)),
                'absensi as alpa_count' => fn ($q) => $q->where('status', 'alpa')->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId)),
            ]);

        match ($sort) {
            'persen_desc' => $query->orderByRaw('(hadir_count * 1.0 / NULLIF(hadir_count + izin_count + sakit_count + alpa_count, 0)) DESC'),
            'persen_asc' => $query->orderByRaw('(hadir_count * 1.0 / NULLIF(hadir_count + izin_count + sakit_count + alpa_count, 0)) ASC'),
            default => $query->orderBy('nama'),
        };

        $filteredByKelas = $request->filled('kelas_id');

        $mahasiswa = $filteredByKelas ? $query->get() : $query->paginate(40)->withQueryString();

        // Totals across every matching student, not just the current page.
        $matchingIds = $applyFilters(Mahasiswa::query())->pluck('id');

        $perStatus = Absensi::whereIn('mahasiswa_id', $matchingIds)
            ->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId))
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $totalHadir = (int) $perStatus->get('hadir', 0);
        $totalIzin = (int) $perStatus->get('izin', 0);
        $totalSakit = (int) $perStatus->get('sakit', 0);
        $totalAlpa = (int) $perStatus->get('alpa', 0);
        $totalTidakHadir = $totalIzin + $totalSakit + $totalAlpa;

        $ringkasan = $this->ringkasanPengisian($matchingIds->count(), $sesiId, $totalHadir + $totalTidakHadir);

        $kelasList = $this->kelasOptions($user)->get();
        $jurusanList = Mahasiswa::query()->select('jurusan')->distinct()->orderBy('jurusan')->pluck('jurusan');
        $sesiList = SesiAbsensi::orderByDesc('tanggal')->get();

        return view('rekap.mahasiswa', compact('mahasiswa', 'kelasList', 'jurusanList', 'sesiList', 'filteredByKelas', 'totalHadir', 'totalIzin', 'totalSakit', 'totalAlpa', 'totalTidakHadir', 'ringkasan', 'sort', 'kehadiran'));
    }

    public function perKelas(Request $request): View
    {
        $user = $request->user();

        $kelasList = $this->kelasOptions($user)
            ->when($request->filled('kelas_id'), fn ($q) => $q->where('id', $request->integer('kelas_id')))
            ->when($request->filled('kategori'), fn ($q) => $q->where('kategori', $request->string('kategori')))
            ->withCount('mahasiswa')
            ->get();

        $counts = Absensi::query()
            ->selectRaw('kelas_id, status, count(*) as total')
            ->when($request->filled('jurusan'), fn ($q) => $q->whereHas('mahasiswa', fn ($q) => $q->where('jurusan', $request->string('jurusan'))))
            ->when($request->filled('sesi'), fn ($q) => $q->where('sesi_absensi_id', $request->integer('sesi')))
            ->when($request->filled('tanggal'), fn ($q) => $q->whereHas('sesiAbsensi', fn ($q) => $q->whereDate('tanggal', $request->date('tanggal'))))
            ->whereIn('kelas_id', $kelasList->pluck('id'))
            ->groupBy('kelas_id', 'status')
            ->get()
            ->groupBy('kelas_id');

        $jumlahSesi = $this->jumlahSesiDihitung($request->integer('sesi') ?: null);

        $rekap = $kelasList->map(function (Kelas $kelas) use ($counts, $jumlahSesi) {
            $byStatus = ($counts->get($kelas->id) ?? collect())->pluck('total', 'status');
            $hadir = $byStatus->get('hadir', 0);
            $izin = $byStatus->get('izin', 0);
            $sakit = $byStatus->get('sakit', 0);
            $alpa = $byStatus->get('alpa', 0);
            $total = $hadir + $izin + $sakit + $alpa;

            return [
                'kelas' => $kelas,
                'hadir' => $hadir,
                'izin' => $izin,
                'sakit' => $sakit,
                'alpa' => $alpa,
                'belum' => max($kelas->mahasiswa_count * $jumlahSesi - $total, 0),
                'persen' => $total > 0 ? round($hadir / $total * 100, 1) : 0,
            ];
        });

        $totalHadir = $rekap->sum('hadir');
        $totalIzin = $rekap->sum('izin');
        $totalSakit = $rekap->sum('sakit');
        $totalAlpa = $rekap->sum('alpa');
        $totalTidakHadir = $totalIzin + $totalSakit + $totalAlpa;
        $totalMaba = $kelasList->sum('mahasiswa_count');

        $ringkasan = $this->ringkasanPengisian($totalMaba, $request->integer('sesi') ?: null, $totalHadir + $totalTidakHadir);

        $sort = $request->string('sort', 'kelas')->toString();

        $rekap = match ($sort) {
            'persen_desc' => $rekap->sortByDesc('persen')->values(),
            'persen_asc' => $rekap->sortBy('persen')->values(),
            default => $rekap,
        };

        $jurusanList = Mahasiswa::query()->select('jurusan')->distinct()->orderBy('jurusan')->pluck('jurusan');
        $sesiList = SesiAbsensi::orderByDesc('tanggal')->get();
        $allKelas = $this->kelasOptions($user)->get();

        return view('rekap.kelas', compact('rekap', 'jurusanList', 'sesiList', 'allKelas', 'totalHadir', 'totalIzin', 'totalSakit', 'totalAlpa', 'totalTidakHadir', 'totalMaba', 'ringkasan', 'sort'));
    }

    public function detailMahasiswa(Request $request, Mahasiswa $mahasiswa): View
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            abort_unless($user->kelas()->where('kelas.id', $mahasiswa->kelas_id)->exists(), 403);
        }

        $mahasiswa->load('kelas');

        $riwayat = Absensi::where('mahasiswa_id', $mahasiswa->id)
            ->with('sesiAbsensi')
            ->get()
            ->sortByDesc(fn (Absensi $a) => $a->sesiAbsensi?->tanggal)
            ->values();

        return view('rekap.detail-mahasiswa', compact('mahasiswa', 'riwayat'));
    }

    private function kelasOptions($user)
    {
        return Kelas::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->whereIn('id', $user->kelas()->pluck('kelas.id')))
            ->orderByRaw("CASE WHEN kategori = 'pagi' THEN 0 ELSE 1 END")->orderBy('nama_kelas');
    }

    /** Berapa sesi yang sedang dihitung: 1 kalau difilter, kalau tidak semua sesi. */
    private function jumlahSesiDihitung(?int $sesiId): int
    {
        return $sesiId ? 1 : SesiAbsensi::count();
    }

    /**
     * Seberapa lengkap absensi terisi: tiap maba dikali jumlah sesi yang dihitung
     * adalah jumlah keterangan yang seharusnya ada.
     *
     * @return array<string, int|float>
     */
    private function ringkasanPengisian(int $totalMaba, ?int $sesiId, int $sudahDiabsen): array
    {
        $jumlahSesi = $this->jumlahSesiDihitung($sesiId);
        $seharusnya = $totalMaba * $jumlahSesi;

        return [
            'total_maba' => $totalMaba,
            'jumlah_sesi' => $jumlahSesi,
            'seharusnya' => $seharusnya,
            'sudah_diabsen' => $sudahDiabsen,
            'belum_diabsen' => max($seharusnya - $sudahDiabsen, 0),
            'persen' => $seharusnya > 0 ? round($sudahDiabsen / $seharusnya * 100, 1) : 0.0,
            'lengkap' => $seharusnya > 0 && $sudahDiabsen >= $seharusnya,
        ];
    }
}
