<?php

namespace App\Exports;

use App\Models\Mahasiswa;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class PoinPelanggaranExport
{
    /**
     * @param  Collection<int, Mahasiswa>  $mahasiswa  sudah di-withCount absensi + withSum pelanggaran
     * @param  Collection<int, \App\Models\Pelanggaran>  $pelanggaran  dikelompokkan per mahasiswa_id
     */
    public function build(Collection $mahasiswa, Collection $pelanggaran): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        $this->buildRekapSheet($spreadsheet, $mahasiswa);
        $this->buildDetailSheet($spreadsheet, $mahasiswa, $pelanggaran);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function buildRekapSheet(Spreadsheet $spreadsheet, Collection $mahasiswa): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Poin');

        $header = ['Nama', 'NIM', 'Jurusan', 'Kelas', 'Poin Absensi', 'Poin Pelanggaran', 'Total Poin', 'Sanksi'];
        $sheet->fromArray($header, null, 'A1', true);
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        $rows = $mahasiswa->map(fn (Mahasiswa $m) => [
            $m->nama,
            $m->nim,
            $m->jurusan,
            $m->kelas->nama_kelas,
            $m->poin(),
            $m->poinPelanggaran(),
            $m->totalPoin(),
            $m->sanksi()['label'],
        ])->all();

        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2', true);
        }

        $this->sizeColumns($sheet, textColumns: 4, totalColumns: count($header));
    }

    private function buildDetailSheet(Spreadsheet $spreadsheet, Collection $mahasiswa, Collection $pelanggaran): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Detail Pelanggaran');

        $header = ['Nama', 'NIM', 'Kelas', 'Jenis Pelanggaran', 'Poin', 'Dicatat Oleh', 'Tanggal'];
        $sheet->fromArray($header, null, 'A1', true);
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        $rows = [];

        foreach ($mahasiswa as $m) {
            foreach ($pelanggaran->get($m->id, collect()) as $p) {
                $rows[] = [
                    $m->nama,
                    $m->nim,
                    $m->kelas->nama_kelas,
                    $p->jenis,
                    $p->poin,
                    $p->pencatat?->name ?? '-',
                    $p->created_at?->translatedFormat('d M Y H:i') ?? '-',
                ];
            }
        }

        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2', true);
        }

        $this->sizeColumns($sheet, textColumns: 4, totalColumns: count($header));
    }

    /** Auto-size hanya kolom teks; sisanya lebar tetap supaya export tetap cepat. */
    private function sizeColumns($sheet, int $textColumns, int $totalColumns): void
    {
        foreach (range(1, $textColumns) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        foreach (range($textColumns + 1, $totalColumns) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth(18);
        }
    }
}
