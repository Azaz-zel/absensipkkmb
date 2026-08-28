<?php

namespace App\Imports;

use PhpOffice\PhpSpreadsheet\IOFactory;

class MahasiswaImport
{
    /**
     * Parse an uploaded spreadsheet into raw rows keyed by column name.
     * Row 1 is always treated as the header and skipped.
     *
     * @return array<int, array{baris: int, nama: string, nim: string, jurusan: string, kelas: string}>
     */
    public function readRows(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = [];

        foreach ($sheet->toArray(null, true, true, false) as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $isBlank = collect($row)->every(fn ($value) => trim((string) $value) === '');

            if ($isBlank) {
                continue;
            }

            $rows[] = [
                'baris' => $index + 1,
                'nama' => trim((string) ($row[0] ?? '')),
                'nim' => trim((string) ($row[1] ?? '')),
                'jurusan' => trim((string) ($row[2] ?? '')),
                'kelas' => strtoupper(trim((string) ($row[3] ?? ''))),
            ];
        }

        return $rows;
    }
}
