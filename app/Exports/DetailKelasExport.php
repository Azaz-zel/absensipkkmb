<?php

namespace App\Exports;

use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\SesiAbsensi;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class DetailKelasExport
{
    private const STATUS_LABELS = ['Hadir', 'Izin', 'Sakit', 'Alpa'];

    public function build(Kelas $kelas, Collection $mahasiswa, Collection $sesiList, Collection $statusMatrix, ?SesiAbsensi $singleSesi = null): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Detail Kelas '.$kelas->nama_kelas);

        $label = match (true) {
            $singleSesi !== null => $singleSesi->nama_sesi,
            $sesiList->isNotEmpty() => 'Semua Sesi',
            default => 'Belum ada sesi',
        };

        $fixedHeadersBefore = ['No', 'Nama', 'NIM', 'Jurusan', 'Kelas'];
        $fixedHeadersAfter = ['Poin'];

        $totalColumns = count($fixedHeadersBefore) + ($sesiList->count() * count(self::STATUS_LABELS)) + count($fixedHeadersAfter);
        $lastColumn = Coordinate::stringFromColumnIndex(max($totalColumns, 1));

        $sheet->setCellValue('A1', 'Sesi: '.$label);
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setItalic(true);

        $col = 1;

        foreach ($fixedHeadersBefore as $headerLabel) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue("{$letter}2", $headerLabel);
            $sheet->mergeCells("{$letter}2:{$letter}3");
            $col++;
        }

        foreach ($sesiList as $sesi) {
            $startLetter = Coordinate::stringFromColumnIndex($col);
            $endLetter = Coordinate::stringFromColumnIndex($col + count(self::STATUS_LABELS) - 1);
            $sheet->setCellValue("{$startLetter}2", $sesi->nama_sesi);
            $sheet->mergeCells("{$startLetter}2:{$endLetter}2");

            foreach (self::STATUS_LABELS as $statusLabel) {
                $letter = Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue("{$letter}3", $statusLabel);
                $col++;
            }
        }

        foreach ($fixedHeadersAfter as $headerLabel) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue("{$letter}2", $headerLabel);
            $sheet->mergeCells("{$letter}2:{$letter}3");
            $col++;
        }

        $sheet->getStyle("A2:{$lastColumn}3")->getFont()->setBold(true);
        $sheet->getStyle("A2:{$lastColumn}3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = 4;
        $no = 1;

        foreach ($mahasiswa as $m) {
            $rowData = [$no, $m->nama, $m->nim, $m->jurusan, $kelas->nama_kelas];
            $totalPoin = 0;

            foreach ($sesiList as $sesi) {
                $status = $statusMatrix->get($m->id, collect())->get($sesi->id);
                $rowData[] = $status === 'hadir' ? 1 : 0;
                $rowData[] = $status === 'izin' ? 1 : 0;
                $rowData[] = $status === 'sakit' ? 1 : 0;
                $rowData[] = $status === 'alpa' ? 1 : 0;

                $totalPoin += Mahasiswa::poinUntukStatus($status);
            }

            $rowData[] = $totalPoin;

            $sheet->fromArray($rowData, null, "A{$row}", true);

            $row++;
            $no++;
        }

        foreach (range(1, max($totalColumns, 1)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
