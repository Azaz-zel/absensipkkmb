<?php

namespace Database\Seeders;

use App\Models\Kelas;
use Illuminate\Database\Seeder;

class KelasSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [];

        foreach (range('A', 'W') as $huruf) {
            $rows[] = [
                'nama_kelas' => $huruf,
                'kategori' => 'pagi',
                'kapasitas' => null,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (range('A', 'I') as $huruf) {
            $rows[] = [
                'nama_kelas' => 'A'.$huruf,
                'kategori' => 'malam',
                'kapasitas' => null,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $namaKelasValid = array_column($rows, 'nama_kelas');

        Kelas::query()->whereNotIn('nama_kelas', $namaKelasValid)->delete();
        Kelas::query()->upsert($rows, ['nama_kelas'], ['kategori', 'kapasitas', 'status', 'updated_at']);
    }
}
