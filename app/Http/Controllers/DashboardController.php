<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\SesiAbsensi;
use App\Models\SesiKelas;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $stats = [
                'total_maba' => Mahasiswa::count(),
                'total_kelas_pagi' => Kelas::where('kategori', 'pagi')->count(),
                'total_kelas_malam' => Kelas::where('kategori', 'malam')->count(),
                'total_kehadiran' => Absensi::where('status', 'hadir')->count(),
                'tidak_hadir' => Absensi::whereIn('status', ['izin', 'sakit', 'alpa'])->count(),
                'sesi_aktif' => SesiAbsensi::whereHas('sesiKelas', fn ($q) => $q->where('status', 'dibuka'))->count(),
            ];

            $sesiTerbaru = SesiAbsensi::latest('tanggal')->limit(5)->withCount([
                'sesiKelas as dibuka_count' => fn ($q) => $q->where('status', 'dibuka'),
                'sesiKelas as ditutup_count' => fn ($q) => $q->where('status', 'ditutup'),
                'sesiKelas as belum_dibuka_count' => fn ($q) => $q->where('status', 'belum_dibuka'),
            ])->get();

            return view('dashboard.admin', compact('stats', 'sesiTerbaru'));
        }

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
}
