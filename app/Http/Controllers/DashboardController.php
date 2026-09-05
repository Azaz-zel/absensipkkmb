<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\Pelanggaran;
use App\Models\SesiAbsensi;
use App\Models\SesiKelas;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return match (true) {
            $user->isManajer() => $this->indexForManajer(),
            $user->isAdmin() => $this->indexForAdmin(),
            default => $this->indexForPj($user),
        };
    }

    private function indexForAdmin(): View
    {
        $mahasiswa = $this->mahasiswaDenganPoin();
        $sebaran = $this->sebaranSanksi($mahasiswa);

        $kehadiran = Absensi::selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $stats = [
            'total_maba' => $mahasiswa->count(),
            'total_kelas_pagi' => Kelas::where('kategori', 'pagi')->count(),
            'total_kelas_malam' => Kelas::where('kategori', 'malam')->count(),
            'total_kehadiran' => (int) $kehadiran->get('hadir', 0),
            'tidak_hadir' => (int) $kehadiran->only(['izin', 'sakit', 'alpa'])->sum(),
            'sesi_aktif' => SesiAbsensi::whereHas('sesiKelas', fn ($q) => $q->where('status', 'dibuka'))->count(),
            'kena_poin' => $mahasiswa->count() - $sebaran['aman'],
            'perlu_sanksi_berat' => $sebaran['berat'] + $sebaran['merah'],
            'total_pj' => User::where('role', 'pj')->where('status', 'aktif')->count(),
            'pelanggaran_hari_ini' => Pelanggaran::whereDate('created_at', today())->count(),
        ];

        $sesiTerbaru = SesiAbsensi::latest('tanggal')->limit(5)->withCount([
            'sesiKelas as dibuka_count' => fn ($q) => $q->where('status', 'dibuka'),
            'sesiKelas as ditutup_count' => fn ($q) => $q->where('status', 'ditutup'),
            'sesiKelas as belum_dibuka_count' => fn ($q) => $q->where('status', 'belum_dibuka'),
        ])->get();

        return view('dashboard.admin', [
            'stats' => $stats,
            'sebaran' => $sebaran,
            'kehadiran' => $kehadiran,
            'topPoin' => $this->topPoin($mahasiswa),
            'pelanggaranTerbaru' => $this->pelanggaranTerbaru(6),
            'sesiTerbaru' => $sesiTerbaru,
            'sesiSorotan' => $sesiTerbaru->first(),
            'progresSesi' => $this->progresSesi($sesiTerbaru->first(), $mahasiswa->count()),
        ]);
    }

    /**
     * Manajer (sie Kesekre) cuma ngurus poin, jadi dashboardnya soal poin:
     * sebaran sanksi, maba poin tertinggi, dan pelanggaran yang baru dicatat.
     */
    private function indexForManajer(): View
    {
        $mahasiswa = $this->mahasiswaDenganPoin();
        $sebaran = $this->sebaranSanksi($mahasiswa);

        $stats = [
            'total_maba' => $mahasiswa->count(),
            'kena_poin' => $mahasiswa->count() - $sebaran['aman'],
            'aman' => $sebaran['aman'],
            'perlu_sanksi_berat' => $sebaran['berat'] + $sebaran['merah'],
            'total_poin_pelanggaran' => (int) Pelanggaran::sum('poin'),
            'pelanggaran_hari_ini' => Pelanggaran::whereDate('created_at', today())->count(),
        ];

        return view('dashboard.manajer', [
            'stats' => $stats,
            'sebaran' => $sebaran,
            'topPoin' => $this->topPoin($mahasiswa),
            'pelanggaranTerbaru' => $this->pelanggaranTerbaru(8),
        ]);
    }

    private function indexForPj(User $user): View
    {
        $kelasSaya = $user->kelas()->withCount('mahasiswa')->get();

        $sesiAktif = SesiAbsensi::latest('tanggal')->first();
        $statusPerKelas = collect();

        if ($sesiAktif) {
            $statusPerKelas = SesiKelas::where('sesi_absensi_id', $sesiAktif->id)
                ->whereIn('kelas_id', $kelasSaya->pluck('id'))
                ->with('kelas')
                ->get();
        }

        return view('dashboard.pj', compact('kelasSaya', 'sesiAktif', 'statusPerKelas'));
    }

    /** Satu query yang membawa semua alias yang dibutuhkan poin() dan poinPelanggaran(). */
    private function mahasiswaDenganPoin(): Collection
    {
        return Mahasiswa::query()
            ->select(['id', 'nama', 'nim', 'jurusan', 'kelas_id'])
            ->with('kelas:id,nama_kelas')
            ->withCount([
                'absensi as izin_count' => fn ($q) => $q->where('status', 'izin'),
                'absensi as sakit_count' => fn ($q) => $q->where('status', 'sakit'),
                'absensi as alpa_count' => fn ($q) => $q->where('status', 'alpa'),
            ])
            ->withSum('pelanggaran', 'poin')
            ->get();
    }

    /** @return array<string, int> */
    private function sebaranSanksi(Collection $mahasiswa): array
    {
        $sebaran = ['aman' => 0, 'ringan' => 0, 'sedang' => 0, 'berat' => 0, 'merah' => 0];

        foreach ($mahasiswa as $m) {
            $sebaran[$m->sanksi()['level']]++;
        }

        return $sebaran;
    }

    private function topPoin(Collection $mahasiswa, int $limit = 10): \Illuminate\Support\Collection
    {
        return $mahasiswa->filter(fn (Mahasiswa $m) => $m->totalPoin() > 0)
            ->sortByDesc(fn (Mahasiswa $m) => $m->totalPoin())
            ->take($limit)
            ->values();
    }

    private function pelanggaranTerbaru(int $limit): Collection
    {
        return Pelanggaran::with(['mahasiswa:id,nama,nim', 'pencatat:id,name'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Seberapa jauh sesi terakhir sudah dikerjakan: berapa kelas yang sudah
     * ditutup dan berapa maba yang sudah punya keterangan.
     *
     * @return array<string, int>|null
     */
    private function progresSesi(?SesiAbsensi $sesi, int $totalMaba): ?array
    {
        if (! $sesi) {
            return null;
        }

        $perStatus = Absensi::where('sesi_absensi_id', $sesi->id)
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $sudahDiabsen = (int) $perStatus->sum();

        return [
            'hadir' => (int) $perStatus->get('hadir', 0),
            'izin' => (int) $perStatus->get('izin', 0),
            'sakit' => (int) $perStatus->get('sakit', 0),
            'alpa' => (int) $perStatus->get('alpa', 0),
            'sudah_diabsen' => $sudahDiabsen,
            'belum_diabsen' => max($totalMaba - $sudahDiabsen, 0),
            'total_maba' => $totalMaba,
        ];
    }
}
