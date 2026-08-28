<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\SesiAbsensi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DetailKelasExportTest extends TestCase
{
    use RefreshDatabase;

    private function seedKelasWithTwoSesi(): array
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $ani = Mahasiswa::create(['nama' => 'Ani', 'nim' => 'ani', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);

        $sesi1 = SesiAbsensi::create(['nama_sesi' => 'Pra PKKMB DAY 1', 'tanggal' => now()]);
        $sesi2 = SesiAbsensi::create(['nama_sesi' => 'Pra PKKMB DAY 2', 'tanggal' => now()->addDay()]);

        Absensi::create(['mahasiswa_id' => $ani->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi1->id, 'status' => 'hadir']);
        Absensi::create(['mahasiswa_id' => $ani->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi2->id, 'status' => 'izin']);

        return [$kelas, $sesi1, $sesi2];
    }

    private function readSheet(\Illuminate\Testing\TestResponse $response): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'detail').'.xlsx';
        file_put_contents($tmpFile, $response->streamedContent());
        $sheet = IOFactory::load($tmpFile)->getActiveSheet();
        unlink($tmpFile);

        return $sheet;
    }

    public function test_export_labels_which_sesi_it_is_for(): void
    {
        [$kelas, $sesi1] = $this->seedKelasWithTwoSesi();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get("/export/kelas/{$kelas->id}?sesi={$sesi1->id}");
        $response->assertOk();

        $sheet = $this->readSheet($response);

        // Fixed columns: No,Nama,NIM,Jurusan,Kelas (A-E). Sesi 1 group starts at F.
        $this->assertSame('Sesi: Pra PKKMB DAY 1', $sheet->getCell('A1')->getValue());
        $this->assertSame('Pra PKKMB DAY 1', $sheet->getCell('F2')->getValue());
        $this->assertSame('Hadir', $sheet->getCell('F3')->getValue());
        $this->assertEquals(1, $sheet->getCell('F4')->getValue());
    }

    public function test_export_follows_the_sesi_filter_actually_selected(): void
    {
        [$kelas, , $sesi2] = $this->seedKelasWithTwoSesi();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get("/export/kelas/{$kelas->id}?sesi={$sesi2->id}");
        $response->assertOk();

        $sheet = $this->readSheet($response);

        $this->assertSame('Sesi: Pra PKKMB DAY 2', $sheet->getCell('A1')->getValue());
        $this->assertSame('Pra PKKMB DAY 2', $sheet->getCell('F2')->getValue());
        $this->assertEquals(1, $sheet->getCell('G4')->getValue()); // Izin sub-column.
    }

    public function test_export_with_no_sesi_filter_includes_every_sesi_as_its_own_column_group(): void
    {
        [$kelas] = $this->seedKelasWithTwoSesi();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get("/export/kelas/{$kelas->id}");
        $response->assertOk();

        $sheet = $this->readSheet($response);

        $this->assertSame('Sesi: Semua Sesi', $sheet->getCell('A1')->getValue());
        $this->assertSame('Pra PKKMB DAY 1', $sheet->getCell('F2')->getValue());
        $this->assertSame('Pra PKKMB DAY 2', $sheet->getCell('J2')->getValue());

        // 1 row for Ani (not 2) — sessions are column groups now, not row groups.
        $row = $sheet->rangeToArray('A4:N4')[0];
        $this->assertSame('Ani', $row[1]);
        $this->assertEquals([1, 0, 0, 0], array_slice($row, 5, 4)); // Sesi 1: hadir.
        $this->assertEquals([0, 1, 0, 0], array_slice($row, 9, 4)); // Sesi 2: izin.
    }

    public function test_export_poin_column_reflects_accumulated_points_across_shown_sessions(): void
    {
        [$kelas] = $this->seedKelasWithTwoSesi();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get("/export/kelas/{$kelas->id}");
        $response->assertOk();

        $sheet = $this->readSheet($response);
        $header = $sheet->rangeToArray('A2:N2')[0];
        $this->assertSame('Poin', end($header));

        // Ani: Sesi 1 hadir (0 poin) + Sesi 2 izin (3 poin) = 3 poin total.
        $this->assertEquals(3, $sheet->getCell('N4')->getValue());
    }

    public function test_unduh_link_on_rekap_kelas_page_forwards_the_selected_sesi_filter(): void
    {
        [$kelas, , $sesi2] = $this->seedKelasWithTwoSesi();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get("/rekap/kelas?sesi={$sesi2->id}");

        $response->assertSee("/export/kelas/{$kelas->id}?", false);
        $response->assertSee("sesi={$sesi2->id}", false);
    }
}
