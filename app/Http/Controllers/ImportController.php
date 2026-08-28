<?php

namespace App\Http\Controllers;

use App\Exports\TemplateMahasiswaExport;
use App\Imports\MahasiswaImport;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    private const CACHE_PREFIX = 'import-mahasiswa:';

    public function create(): View
    {
        return view('mahasiswa.import', ['rows' => null, 'summary' => null, 'token' => null]);
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = (new TemplateMahasiswaExport())->build();
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'template-mahasiswa.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function preview(Request $request): View
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $rawRows = (new MahasiswaImport())->readRows($request->file('file')->getRealPath());

        $kelasMap = Kelas::pluck('id', 'nama_kelas');
        // Soft-deleted mahasiswa don't count as "already registered" — their NIM is free to reuse.
        $existingNim = Mahasiswa::pluck('nim')->flip();
        $nimCounts = collect($rawRows)->countBy('nim');

        $rows = collect($rawRows)->map(function (array $row) use ($kelasMap, $existingNim, $nimCounts) {
            $errors = [];

            if ($row['nama'] === '') {
                $errors[] = 'Nama kosong';
            }

            if ($row['nim'] === '') {
                $errors[] = 'NIM kosong';
            } elseif ($nimCounts->get($row['nim'], 0) > 1) {
                $errors[] = 'NIM duplikat dalam file';
            } elseif ($existingNim->has($row['nim'])) {
                $errors[] = 'NIM sudah terdaftar';
            }

            if ($row['jurusan'] === '') {
                $errors[] = 'Jurusan kosong';
            }

            $kelasId = null;

            if ($row['kelas'] === '') {
                $errors[] = 'Kelas kosong';
            } elseif (! $kelasMap->has($row['kelas'])) {
                $errors[] = 'Kelas tidak ditemukan';
            } else {
                $kelasId = $kelasMap->get($row['kelas']);
            }

            return [...$row, 'kelas_id' => $kelasId, 'valid' => $errors === [], 'errors' => $errors];
        })->all();

        $token = (string) Str::uuid();
        cache()->put(self::CACHE_PREFIX.$token, $rows, now()->addMinutes(30));

        $summary = [
            'total' => count($rows),
            'valid' => collect($rows)->where('valid', true)->count(),
            'error' => collect($rows)->where('valid', false)->count(),
        ];

        return view('mahasiswa.import', compact('rows', 'summary', 'token'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $rows = cache()->pull(self::CACHE_PREFIX.$request->string('token'));

        abort_if($rows === null, 419, 'Sesi import telah kedaluwarsa, silakan upload ulang file Excel.');

        $now = now();
        $insert = collect($rows)->where('valid', true)->map(fn (array $row) => [
            'nama' => $row['nama'],
            'nim' => $row['nim'],
            'jurusan' => $row['jurusan'],
            'kelas_id' => $row['kelas_id'],
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($insert !== []) {
            Mahasiswa::insert($insert);
        }

        $skipped = count($rows) - count($insert);
        $message = count($insert).' mahasiswa berhasil diimport.';

        if ($skipped > 0) {
            $message .= " {$skipped} baris dilewati karena tidak valid.";
        }

        return redirect()->route('mahasiswa.index')->with('success', $message);
    }
}
