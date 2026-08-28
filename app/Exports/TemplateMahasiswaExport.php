<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

class TemplateMahasiswaExport
{
    public function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Mahasiswa');

        $sheet->fromArray(['nama', 'nim', 'jurusan', 'kelas'], null, 'A1', true);
        $sheet->fromArray(['Made Putra', '23010001', 'Teknik Informatika', 'A'], null, 'A2', true);

        $sheet->getStyle('A1:D1')->getFont()->setBold(true);

        foreach (['A', 'B', 'C', 'D'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
