<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\SesiAbsensi;
use App\Models\SesiKelas;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AbsensiController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return $this->indexForPj($user);
        }

        $sesiList = SesiAbsensi::orderByDesc('tanggal')->get();
        $sesiId = $request->integer('sesi') ?: optional($sesiList->first())->id;
        $sesi = $sesiId ? $sesiList->firstWhere('id', $sesiId) : null;

        $kelasList = Kelas::withCount('mahasiswa')
            ->orderByRaw("CASE WHEN kategori = 'pagi' THEN 0 ELSE 1 END")->orderBy('nama_kelas')->get();

        $statusMap = collect();

        if ($sesi) {
            $statusMap = SesiKelas::where('sesi_absensi_id', $sesi->id)->pluck('status', 'kelas_id');
        }

        return view('absensi.index', compact('sesiList', 'sesi', 'kelasList', 'statusMap'));
    }

    /**
     * PJ has few kelas, so instead of picking one sesi at a time, show every
     * sesi at once with their assigned kelas + status underneath each.
     */
    private function indexForPj(User $user): View
    {
        $kelasSaya = $user->kelas()->withCount('mahasiswa')
            ->orderByRaw("CASE WHEN kategori = 'pagi' THEN 0 ELSE 1 END")->orderBy('nama_kelas')
            ->get();

        $sesiList = SesiAbsensi::orderByDesc('tanggal')->get();

        $statusBySesi = SesiKelas::whereIn('kelas_id', $kelasSaya->pluck('id'))
            ->whereIn('sesi_absensi_id', $sesiList->pluck('id'))
            ->get()
            ->groupBy('sesi_absensi_id')
            ->map(fn ($rows) => $rows->pluck('status', 'kelas_id'));

        return view('absensi.index-pj', compact('sesiList', 'kelasSaya', 'statusBySesi'));
    }

    public function show(Request $request, SesiAbsensi $sesi, Kelas $kelas): View
    {
        $sesiKelas = SesiKelas::where('sesi_absensi_id', $sesi->id)->where('kelas_id', $kelas->id)->firstOrFail();

        $mahasiswa = Mahasiswa::where('kelas_id', $kelas->id)
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q');
                $q->where(fn ($q) => $q->where('nama', 'like', "%{$term}%")->orWhere('nim', 'like', "%{$term}%"));
            })
            ->orderBy('nama')
            ->get();

        $absensiMap = Absensi::where('sesi_absensi_id', $sesi->id)->where('kelas_id', $kelas->id)->pluck('status', 'mahasiswa_id');

        $editRequested = $request->boolean('edit');

        return view('absensi.show', compact('sesi', 'kelas', 'sesiKelas', 'mahasiswa', 'absensiMap', 'editRequested'));
    }

    public function update(Request $request, SesiAbsensi $sesi, Kelas $kelas): RedirectResponse
    {
        $sesiKelas = SesiKelas::where('sesi_absensi_id', $sesi->id)->where('kelas_id', $kelas->id)->firstOrFail();

        abort_unless(
            $request->user()->isAdmin() || $sesiKelas->status === 'dibuka',
            403,
            'Sesi absensi untuk kelas ini sedang tidak dibuka.'
        );

        $data = $request->validate([
            'absensi' => ['required', 'array'],
            'absensi.*' => ['nullable', Rule::in(['hadir', 'izin', 'sakit', 'alpa'])],
        ]);

        $validMahasiswaIds = Mahasiswa::where('kelas_id', $kelas->id)->pluck('id')->all();
        $now = now();

        $rows = [];

        foreach ($data['absensi'] as $mahasiswaId => $status) {
            if (! $status || ! in_array((int) $mahasiswaId, $validMahasiswaIds, true)) {
                continue;
            }

            $rows[] = [
                'mahasiswa_id' => $mahasiswaId,
                'sesi_absensi_id' => $sesi->id,
                'kelas_id' => $kelas->id,
                'status' => $status,
                'recorded_by' => $request->user()->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            Absensi::upsert($rows, ['mahasiswa_id', 'sesi_absensi_id'], ['kelas_id', 'status', 'recorded_by', 'updated_at']);
        }

        return redirect()->route('absensi.show', [$sesi, $kelas])->with('success', 'Absensi berhasil disimpan.');
    }

    public function updateRecord(Request $request, Absensi $absensi): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['hadir', 'izin', 'sakit', 'alpa'])],
        ]);

        $absensi->update(['status' => $data['status'], 'recorded_by' => $request->user()->id]);

        return back()->with('success', 'Data kehadiran berhasil diubah.');
    }

    public function destroyRecord(Absensi $absensi): RedirectResponse
    {
        $absensi->delete();

        return back()->with('success', 'Data kehadiran berhasil dihapus.');
    }
}
