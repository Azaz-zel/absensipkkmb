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

class RekapMahasiswaFilterTest extends TestCase
{
    use RefreshDatabase;

    private function seedTwoSesiWithMixedAttendance(): array
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);

        $ani = Mahasiswa::create(['nama' => 'Ani', 'nim' => 'ani', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $budi = Mahasiswa::create(['nama' => 'Budi', 'nim' => 'budi', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);

        $sesi1 = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);
        $sesi2 = SesiAbsensi::create(['nama_sesi' => 'Sesi 2', 'tanggal' => now()->addDay()]);

        // Sesi 1: Ani hadir, Budi izin. Sesi 2: Ani alpa, Budi hadir.
        Absensi::create(['mahasiswa_id' => $ani->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi1->id, 'status' => 'hadir']);
        Absensi::create(['mahasiswa_id' => $budi->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi1->id, 'status' => 'izin']);
        Absensi::create(['mahasiswa_id' => $ani->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi2->id, 'status' => 'alpa']);
        Absensi::create(['mahasiswa_id' => $budi->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi2->id, 'status' => 'hadir']);

        return [$kelas, $sesi1, $sesi2];
    }

    public function test_kehadiran_filter_hadir_returns_only_students_with_a_hadir_record(): void
    {
        $this->seedTwoSesiWithMixedAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/rekap/mahasiswa?kehadiran=hadir');

        $names = $response->viewData('mahasiswa')->pluck('nama')->sort()->values()->all();
        $this->assertSame(['Ani', 'Budi'], $names);
    }

    public function test_kehadiran_filter_scoped_to_a_specific_sesi(): void
    {
        [, $sesi1] = $this->seedTwoSesiWithMixedAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        // In Sesi 1 only Ani was hadir (Budi was izin).
        $response = $this->actingAs($admin)->get("/rekap/mahasiswa?kehadiran=hadir&sesi={$sesi1->id}");

        $names = $response->viewData('mahasiswa')->pluck('nama')->values()->all();
        $this->assertSame(['Ani'], $names);
    }

    public function test_kehadiran_filter_tidak_hadir_groups_izin_sakit_alpa_together(): void
    {
        [, $sesi1] = $this->seedTwoSesiWithMixedAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        // In Sesi 1, Budi was izin — counts as "tidak hadir".
        $response = $this->actingAs($admin)->get("/rekap/mahasiswa?kehadiran=tidak_hadir&sesi={$sesi1->id}");

        $names = $response->viewData('mahasiswa')->pluck('nama')->values()->all();
        $this->assertSame(['Budi'], $names);
    }

    private function readExportSheet(string $url): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get($url);
        $response->assertOk();

        $tmpFile = tempnam(sys_get_temp_dir(), 'rekap').'.xlsx';
        file_put_contents($tmpFile, $response->streamedContent());

        $sheet = IOFactory::load($tmpFile)->getActiveSheet();
        $rows = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().$sheet->getHighestRow());

        unlink($tmpFile);

        return $rows;
    }

    /** Find the column index (0-based) where a session's group of 4 sub-columns starts, by its name in header row 1. */
    private function sesiGroupStart(array $headerRow1, string $sesiName): int
    {
        return array_search($sesiName, $headerRow1, true);
    }

    public function test_export_header_has_a_merged_column_group_per_sesi_no_sesi_or_keterangan_column(): void
    {
        $this->seedTwoSesiWithMixedAttendance();

        $rows = $this->readExportSheet('/export/rekap');
        [$headerRow1, $headerRow2] = $rows;

        $this->assertSame(['Nama', 'NIM', 'Jurusan', 'Kelas'], array_slice($headerRow1, 0, 4));
        $this->assertContains('Sesi 1', $headerRow1);
        $this->assertContains('Sesi 2', $headerRow1);
        $this->assertNotContains('Keterangan', $headerRow1);

        $sesi1Start = $this->sesiGroupStart($headerRow1, 'Sesi 1');
        $this->assertSame(['Hadir', 'Izin', 'Sakit', 'Alpa'], array_slice($headerRow2, $sesi1Start, 4));

        $this->assertSame(['Persentase', 'Poin'], array_slice($headerRow1, -2));
    }

    public function test_export_shows_one_hot_status_per_sesi_column_group(): void
    {
        $this->seedTwoSesiWithMixedAttendance();

        $rows = $this->readExportSheet('/export/rekap');
        $headerRow1 = $rows[0];
        $dataRows = array_slice($rows, 2);

        // 1 row per mahasiswa (not per sesi) now.
        $this->assertCount(2, $dataRows);

        $ani = collect($dataRows)->firstWhere(0, 'Ani');
        $sesi1Start = $this->sesiGroupStart($headerRow1, 'Sesi 1');
        $sesi2Start = $this->sesiGroupStart($headerRow1, 'Sesi 2');

        // Ani: Sesi 1 hadir -> [1,0,0,0]. Sesi 2 alpa -> [0,0,0,1].
        $this->assertEquals([1, 0, 0, 0], array_slice($ani, $sesi1Start, 4));
        $this->assertEquals([0, 0, 0, 1], array_slice($ani, $sesi2Start, 4));
    }

    public function test_export_filtered_to_one_sesi_only_includes_that_sesis_column_group(): void
    {
        [, $sesi1] = $this->seedTwoSesiWithMixedAttendance();

        $rows = $this->readExportSheet("/export/rekap?sesi={$sesi1->id}");
        $headerRow1 = $rows[0];

        $this->assertContains('Sesi 1', $headerRow1);
        $this->assertNotContains('Sesi 2', $headerRow1);

        $dataRows = array_slice($rows, 2);
        $this->assertCount(2, $dataRows);
    }

    public function test_active_filter_badges_show_on_the_page_when_filters_applied(): void
    {
        [, $sesi1] = $this->seedTwoSesiWithMixedAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $noFilter = $this->actingAs($admin)->get('/rekap/mahasiswa');
        $noFilter->assertDontSee('Filter aktif');

        $withFilter = $this->actingAs($admin)->get("/rekap/mahasiswa?sesi={$sesi1->id}&kehadiran=hadir");
        $withFilter->assertSee('Filter aktif');
        $withFilter->assertSee('Sesi 1', false);
        $withFilter->assertSee('Kehadiran: Hadir', false);
    }
}
