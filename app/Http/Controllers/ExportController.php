<?php

namespace App\Http\Controllers;

use App\Exports\DetailKelasExport;
use App\Exports\PoinPelanggaranExport;
use App\Exports\RekapAbsensiExport;
use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\Pelanggaran;
use App\Models\SesiAbsensi;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function rekap(Request $request): StreamedResponse
    {
        $sesiId = $request->integer('sesi') ?: null;
        $kehadiran = $request->string('kehadiran')->toString();

        $mahasiswa = Mahasiswa::query()
            ->with('kelas')
            ->withCount([
                'absensi as hadir_count' => fn ($q) => $q->where('status', 'hadir')->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId)),
                'absensi as izin_count' => fn ($q) => $q->where('status', 'izin')->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId)),
                'absensi as sakit_count' => fn ($q) => $q->where('status', 'sakit')->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId)),
                'absensi as alpa_count' => fn ($q) => $q->where('status', 'alpa')->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId)),
            ])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q');
                $q->where(fn ($q) => $q->where('nama', 'like', "%{$term}%")->orWhere('nim', 'like', "%{$term}%"));
            })
            ->when($request->filled('kelas_id'), fn ($q) => $q->where('kelas_id', $request->integer('kelas_id')))
            ->when($request->filled('kategori'), fn ($q) => $q->whereHas('kelas', fn ($q) => $q->where('kategori', $request->string('kategori'))))
            ->when($request->filled('jurusan'), fn ($q) => $q->where('jurusan', $request->string('jurusan')))
            ->when($kehadiran !== '', function ($q) use ($sesiId, $kehadiran) {
                $q->whereHas('absensi', function ($q) use ($sesiId, $kehadiran) {
                    $q->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId));
                    $kehadiran === 'hadir'
                        ? $q->where('status', 'hadir')
                        : $q->whereIn('status', ['izin', 'sakit', 'alpa']);
                });
            })
            ->orderBy('nama')
            ->get();

        $sesiList = $sesiId
            ? SesiAbsensi::where('id', $sesiId)->get()
            : SesiAbsensi::orderBy('tanggal')->get();

        $statusPerSesi = Absensi::whereIn('mahasiswa_id', $mahasiswa->pluck('id'))
            ->get(['mahasiswa_id', 'sesi_absensi_id', 'status'])
            ->groupBy('mahasiswa_id')
            ->map(fn ($rows) => $rows->pluck('status', 'sesi_absensi_id'));

        $spreadsheet = (new RekapAbsensiExport())->build($mahasiswa, $sesiList, $statusPerSesi);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'rekap-absensi.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function poin(Request $request): StreamedResponse
    {
        $sesiId = $request->integer('sesi') ?: null;

        $mahasiswa = Mahasiswa::query()
            ->with('kelas')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q');
                $q->where(fn ($q) => $q->where('nama', 'like', "%{$term}%")->orWhere('nim', 'like', "%{$term}%"));
            })
            ->when($request->filled('kelas_id'), fn ($q) => $q->where('kelas_id', $request->integer('kelas_id')))
            ->withCount([
                'absensi as hadir_count' => fn ($q) => $q->where('status', 'hadir')->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId)),
                'absensi as izin_count' => fn ($q) => $q->where('status', 'izin')->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId)),
                'absensi as sakit_count' => fn ($q) => $q->where('status', 'sakit')->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId)),
                'absensi as alpa_count' => fn ($q) => $q->where('status', 'alpa')->when($sesiId, fn ($q) => $q->where('sesi_absensi_id', $sesiId)),
            ])
            ->withSum('pelanggaran', 'poin')
            ->orderBy('nama')
            ->get();

        $pelanggaran = Pelanggaran::whereIn('mahasiswa_id', $mahasiswa->pluck('id'))
            ->with('pencatat')
            ->latest()
            ->get()
            ->groupBy('mahasiswa_id');

        $spreadsheet = (new PoinPelanggaranExport())->build($mahasiswa, $pelanggaran);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'poin-pelanggaran.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function detailKelas(Request $request, Kelas $kelas): StreamedResponse
    {
        $mahasiswa = Mahasiswa::where('kelas_id', $kelas->id)->orderBy('nama')->get();

        $sesi = match (true) {
            $request->filled('sesi') => SesiAbsensi::find($request->integer('sesi')),
            $request->filled('tanggal') => SesiAbsensi::whereDate('tanggal', $request->date('tanggal'))->orderByDesc('tanggal')->first(),
            default => null,
        };

        $sesiList = $sesi ? collect([$sesi]) : SesiAbsensi::orderBy('tanggal')->get();

        $statusMatrix = Absensi::where('kelas_id', $kelas->id)
            ->whereIn('sesi_absensi_id', $sesiList->pluck('id'))
            ->get(['mahasiswa_id', 'sesi_absensi_id', 'status'])
            ->groupBy('mahasiswa_id')
            ->map(fn ($rows) => $rows->pluck('status', 'sesi_absensi_id'));

        $spreadsheet = (new DetailKelasExport())->build($kelas, $mahasiswa, $sesiList, $statusMatrix, $sesi);
        $writer = new Xlsx($spreadsheet);

        $sesiSlug = $sesi ? '-'.Str::slug($sesi->nama_sesi) : '-semua-sesi';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, "detail-kelas-{$kelas->nama_kelas}{$sesiSlug}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
