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

class RekapMahasiswaPoinTest extends TestCase
{
    use RefreshDatabase;

    private function seedStudentWithMixedAttendance(): Mahasiswa
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $mahasiswa = Mahasiswa::create(['nama' => 'Budi', 'nim' => 'budi', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);

        $sesi1 = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);
        $sesi2 = SesiAbsensi::create(['nama_sesi' => 'Sesi 2', 'tanggal' => now()->addDay()]);
        $sesi3 = SesiAbsensi::create(['nama_sesi' => 'Sesi 3', 'tanggal' => now()->addDays(2)]);
        $sesi4 = SesiAbsensi::create(['nama_sesi' => 'Sesi 4', 'tanggal' => now()->addDays(3)]);

        // 2 alpa (2*5=10) + 1 sakit + 1 izin (2*3=6) = 16 poin total.
        Absensi::create(['mahasiswa_id' => $mahasiswa->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi1->id, 'status' => 'alpa']);
        Absensi::create(['mahasiswa_id' => $mahasiswa->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi2->id, 'status' => 'alpa']);
        Absensi::create(['mahasiswa_id' => $mahasiswa->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi3->id, 'status' => 'sakit']);
        Absensi::create(['mahasiswa_id' => $mahasiswa->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi4->id, 'status' => 'izin']);

        return $mahasiswa;
    }

    public function test_poin_accumulates_per_occurrence_alpa_5_sakit_izin_3(): void
    {
        $mahasiswa = $this->seedStudentWithMixedAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/rekap/mahasiswa');

        $found = $response->viewData('mahasiswa')->firstWhere('id', $mahasiswa->id);
        $this->assertSame(16, $found->poin());
    }

    public function test_student_with_only_hadir_has_zero_poin(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $mahasiswa = Mahasiswa::create(['nama' => 'Ani', 'nim' => 'ani', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);
        Absensi::create(['mahasiswa_id' => $mahasiswa->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi->id, 'status' => 'hadir']);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $response = $this->actingAs($admin)->get('/rekap/mahasiswa');

        $found = $response->viewData('mahasiswa')->firstWhere('id', $mahasiswa->id);
        $this->assertSame(0, $found->poin());
    }

    public function test_poin_shown_in_rekap_mahasiswa_page(): void
    {
        $this->seedStudentWithMixedAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/rekap/mahasiswa');

        $response->assertSee('Poin');
        $response->assertSee('16');
    }

    public function test_excel_export_shows_one_poin_column_with_the_running_total(): void
    {
        $mahasiswa = $this->seedStudentWithMixedAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/export/rekap');
        $response->assertOk();

        $tmpFile = tempnam(sys_get_temp_dir(), 'rekap').'.xlsx';
        file_put_contents($tmpFile, $response->streamedContent());

        $sheet = IOFactory::load($tmpFile)->getActiveSheet();
        $rows = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().$sheet->getHighestRow());

        unlink($tmpFile);

        $headerRow1 = $rows[0];
        $this->assertSame(['Persentase', 'Poin'], array_slice($headerRow1, -2));

        $poinIndex = array_search('Poin', $headerRow1, true);

        // 1 row per mahasiswa now (data starts at row index 2, after the two header rows).
        $budiRow = collect(array_slice($rows, 2))->firstWhere(0, $mahasiswa->nama);

        $this->assertEquals(16, $budiRow[$poinIndex]);
    }
}
