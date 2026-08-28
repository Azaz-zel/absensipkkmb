<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\SesiAbsensi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapMahasiswaSortTest extends TestCase
{
    use RefreshDatabase;

    private function seedThreeStudentsWithDifferentAttendance(): Kelas
    {
        // Alphabetically: Ani, Budi, Citra — but attendance from worst to best: Citra, Ani, Budi.
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);

        $ani = Mahasiswa::create(['nama' => 'Ani', 'nim' => 'ani', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $budi = Mahasiswa::create(['nama' => 'Budi', 'nim' => 'budi', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $citra = Mahasiswa::create(['nama' => 'Citra', 'nim' => 'citra', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);

        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);

        Absensi::create(['mahasiswa_id' => $ani->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi->id, 'status' => 'izin']); // 0%
        Absensi::create(['mahasiswa_id' => $budi->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi->id, 'status' => 'hadir']); // 100%
        Absensi::create(['mahasiswa_id' => $citra->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi->id, 'status' => 'alpa']); // 0%

        return $kelas;
    }

    public function test_default_sort_is_name_alphabetical_unfiltered(): void
    {
        $this->seedThreeStudentsWithDifferentAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/rekap/mahasiswa');

        $names = $response->viewData('mahasiswa')->pluck('nama')->values()->all();
        $this->assertSame(['Ani', 'Budi', 'Citra'], $names);
    }

    public function test_sort_by_percentage_descending_unfiltered(): void
    {
        $this->seedThreeStudentsWithDifferentAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/rekap/mahasiswa?sort=persen_desc');

        $names = $response->viewData('mahasiswa')->pluck('nama')->values()->all();
        $this->assertSame('Budi', $names[0]);
    }

    public function test_sort_by_percentage_ascending_when_filtered_by_kelas(): void
    {
        $kelas = $this->seedThreeStudentsWithDifferentAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        // This branch uses ->get() (full collection) instead of ->paginate() — must sort the same way.
        $response = $this->actingAs($admin)->get("/rekap/mahasiswa?kelas_id={$kelas->id}&sort=persen_asc");

        $names = $response->viewData('mahasiswa')->pluck('nama')->values()->all();
        $this->assertSame('Budi', end($names));
    }
}
