<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class RekapAbsensiExport
{
    private const STATUS_LABELS = ['Hadir', 'Izin', 'Sakit', 'Alpa'];

    public function build(Collection $mahasiswa, Collection $sesiList, Collection $statusPerSesi): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Absensi');

        $fixedHeadersBefore = ['Nama', 'NIM', 'Jurusan', 'Kelas'];
        $fixedHeadersAfter = ['Persentase', 'Poin'];

        $totalColumns = count($fixedHeadersBefore) + ($sesiList->count() * count(self::STATUS_LABELS)) + count($fixedHeadersAfter);
        $lastColumn = Coordinate::stringFromColumnIndex(max($totalColumns, 1));

        $col = 1;

        foreach ($fixedHeadersBefore as $label) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue("{$letter}1", $label);
            $sheet->mergeCells("{$letter}1:{$letter}2");
            $col++;
        }

        foreach ($sesiList as $sesi) {
            $startLetter = Coordinate::stringFromColumnIndex($col);
            $endLetter = Coordinate::stringFromColumnIndex($col + count(self::STATUS_LABELS) - 1);
            $sheet->setCellValue("{$startLetter}1", $sesi->nama_sesi);
            $sheet->mergeCells("{$startLetter}1:{$endLetter}1");

            foreach (self::STATUS_LABELS as $label) {
                $letter = Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue("{$letter}2", $label);
                $col++;
            }
        }

        foreach ($fixedHeadersAfter as $label) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue("{$letter}1", $label);
            $sheet->mergeCells("{$letter}1:{$letter}2");
            $col++;
        }

        $sheet->getStyle("A1:{$lastColumn}2")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sesiIds = $sesiList->pluck('id')->all();
        $rows = [];

        foreach ($mahasiswa as $m) {
            $total = $m->hadir_count + $m->izin_count + $m->sakit_count + $m->alpa_count;
            $persen = $total > 0 ? round($m->hadir_count / $total * 100, 1) : 0;

            $rowData = [$m->nama, $m->nim, $m->jurusan, $m->kelas->nama_kelas];
            $statusesForStudent = $statusPerSesi->get($m->id, collect());

            foreach ($sesiIds as $sesiId) {
                $status = $statusesForStudent->get($sesiId);
                $rowData[] = $status === 'hadir' ? 1 : 0;
                $rowData[] = $status === 'izin' ? 1 : 0;
                $rowData[] = $status === 'sakit' ? 1 : 0;
                $rowData[] = $status === 'alpa' ? 1 : 0;
            }

            $rowData[] = $persen.'%';
            $rowData[] = $m->poin();

            $rows[] = $rowData;
        }

        // One bulk write beats thousands of per-row calls on large exports.
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A3', true);
        }

        // Auto-sizing every column forces a text-width calculation per cell, which dominates
        // the runtime on big exports. Only the text columns actually need it.
        foreach (range(1, count($fixedHeadersBefore)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        foreach (range(count($fixedHeadersBefore) + 1, max($totalColumns, 1)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth(11);
        }

        return $spreadsheet;
    }
}
