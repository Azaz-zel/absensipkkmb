<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\Pelanggaran;
use App\Models\SesiAbsensi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PoinPelanggaranTest extends TestCase
{
    use RefreshDatabase;

    private function seedMahasiswa(string $nama = 'Budi', string $nim = '2026001'): Mahasiswa
    {
        $kelas = Kelas::firstOrCreate(
            ['nama_kelas' => 'A'],
            ['kategori' => 'pagi', 'status' => 'aktif']
        );

        return Mahasiswa::create([
            'nama' => $nama, 'nim' => $nim, 'jurusan' => 'TI', 'kelas_id' => $kelas->id,
        ]);
    }

    private function beriAbsensi(Mahasiswa $mahasiswa, string $status): void
    {
        $sesi = SesiAbsensi::create(['nama_sesi' => uniqid('Sesi '), 'tanggal' => now()]);

        Absensi::create([
            'mahasiswa_id' => $mahasiswa->id,
            'kelas_id' => $mahasiswa->kelas_id,
            'sesi_absensi_id' => $sesi->id,
            'status' => $status,
        ]);
    }

    /** Ambil ulang lewat query yang membawa alias withCount/withSum seperti di controller. */
    private function segarkan(Mahasiswa $mahasiswa): Mahasiswa
    {
        return Mahasiswa::withCount([
            'absensi as izin_count' => fn ($q) => $q->where('status', 'izin'),
            'absensi as sakit_count' => fn ($q) => $q->where('status', 'sakit'),
            'absensi as alpa_count' => fn ($q) => $q->where('status', 'alpa'),
        ])->withSum('pelanggaran', 'poin')->findOrFail($mahasiswa->id);
    }

    public static function tingkatSanksi(): array
    {
        return [
            'nol poin aman' => [0, 'aman', 'Tidak ada sanksi'],
            'batas bawah ringan' => [1, 'ringan', 'PKKMB JOURNEY'],
            'batas atas ringan' => [5, 'ringan', 'PKKMB JOURNEY'],
            'batas bawah sedang' => [6, 'sedang', 'Edukasi Dijitalisasi'],
            'batas atas sedang' => [9, 'sedang', 'Edukasi Dijitalisasi'],
            'batas bawah berat' => [10, 'berat', 'Problem and Solution'],
            'batas atas berat' => [19, 'berat', 'Problem and Solution'],
            'batas bawah cap merah' => [20, 'merah', 'Cap merah'],
            'di atas cap merah' => [21, 'merah', 'Cap merah'],
        ];
    }

    #[DataProvider('tingkatSanksi')]
    public function test_sanksi_untuk_poin_matches_each_tier_boundary(int $poin, string $level, string $label): void
    {
        $sanksi = Mahasiswa::sanksiUntukPoin($poin);

        $this->assertSame($level, $sanksi['level']);
        $this->assertStringContainsString($label, $sanksi['label']);
    }

    public function test_total_poin_sums_attendance_and_violation_points(): void
    {
        $mahasiswa = $this->seedMahasiswa();
        $this->beriAbsensi($mahasiswa, 'alpa');   // 5
        $this->beriAbsensi($mahasiswa, 'sakit');  // 3

        Pelanggaran::create(['mahasiswa_id' => $mahasiswa->id, 'jenis' => 'Telat apel', 'poin' => 4]);

        $segar = $this->segarkan($mahasiswa);

        $this->assertSame(8, $segar->poin());
        $this->assertSame(4, $segar->poinPelanggaran());
        $this->assertSame(12, $segar->totalPoin());
        $this->assertSame('berat', $segar->sanksi()['level']);
    }

    public function test_recording_a_violation_adds_to_the_total_instead_of_replacing_it(): void
    {
        $mahasiswa = $this->seedMahasiswa();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)
            ->post("/poin/{$mahasiswa->id}/catat", ['jenis' => 'Telat apel', 'poin' => 5])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post("/poin/{$mahasiswa->id}/catat", ['jenis' => 'Atribut kurang', 'poin' => 3])
            ->assertRedirect();

        $this->assertSame(2, Pelanggaran::where('mahasiswa_id', $mahasiswa->id)->count());
        $this->assertSame(8, $this->segarkan($mahasiswa)->totalPoin());
    }

    public function test_violation_records_who_logged_it(): void
    {
        $mahasiswa = $this->seedMahasiswa();
        $manajer = User::factory()->create(['role' => 'manajer', 'status' => 'aktif']);

        $this->actingAs($manajer)->post("/poin/{$mahasiswa->id}/catat", ['jenis' => 'Telat', 'poin' => 2]);

        $this->assertSame($manajer->id, Pelanggaran::first()->dicatat_oleh);
    }

    public function test_violation_requires_jenis_and_positive_poin(): void
    {
        $mahasiswa = $this->seedMahasiswa();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)
            ->post("/poin/{$mahasiswa->id}/catat", ['jenis' => '', 'poin' => 5])
            ->assertSessionHasErrors('jenis');

        $this->actingAs($admin)
            ->post("/poin/{$mahasiswa->id}/catat", ['jenis' => 'Telat', 'poin' => 0])
            ->assertSessionHasErrors('poin');

        $this->assertSame(0, Pelanggaran::count());
    }

    public function test_violation_points_are_capped_at_twenty(): void
    {
        $mahasiswa = $this->seedMahasiswa();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)
            ->post("/poin/{$mahasiswa->id}/catat", ['jenis' => 'Kelewatan', 'poin' => 21])
            ->assertSessionHasErrors('poin');

        $this->assertSame(0, Pelanggaran::count());

        $this->actingAs($admin)
            ->post("/poin/{$mahasiswa->id}/catat", ['jenis' => 'Bawa barang terlarang', 'poin' => 20])
            ->assertSessionHasNoErrors();

        $this->assertSame(20, $this->segarkan($mahasiswa)->totalPoin());
        $this->assertSame('merah', $this->segarkan($mahasiswa)->sanksi()['level']);
    }

    public function test_poin_inputs_advertise_the_twenty_point_cap(): void
    {
        $this->seedMahasiswa('Budi', '2026001');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $manajer = User::factory()->create(['role' => 'manajer', 'status' => 'aktif']);

        $this->actingAs($admin)->get('/poin')
            ->assertSee('data-poin-maks="20"', false)
            ->assertSee('maks 20');

        $this->actingAs($manajer)->get('/poin?q=2026001')
            ->assertSee('data-poin-maks="20"', false)
            ->assertSee('maks 20');
    }

    public function test_admin_poin_page_lists_students_with_total_and_sanksi(): void
    {
        $mahasiswa = $this->seedMahasiswa('Budi Santoso', '2026001');
        $this->beriAbsensi($mahasiswa, 'alpa');
        Pelanggaran::create(['mahasiswa_id' => $mahasiswa->id, 'jenis' => 'Telat apel', 'poin' => 2]);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $response = $this->actingAs($admin)->get('/poin');

        $response->assertOk();
        $response->assertViewIs('poin.index');
        $response->assertSee('Budi Santoso');
        $response->assertSee('Edukasi Dijitalisasi');
    }

    public function test_admin_can_sort_by_highest_total_poin(): void
    {
        $ringan = $this->seedMahasiswa('Ani', '2026002');
        $berat = $this->seedMahasiswa('Budi', '2026003');
        Pelanggaran::create(['mahasiswa_id' => $ringan->id, 'jenis' => 'Telat', 'poin' => 1]);
        Pelanggaran::create(['mahasiswa_id' => $berat->id, 'jenis' => 'Telat', 'poin' => 15]);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $response = $this->actingAs($admin)->get('/poin?sort=poin_desc');

        $urutan = collect($response->viewData('mahasiswa')->items())->pluck('id')->all();

        $this->assertSame([$berat->id, $ringan->id], $urutan);
    }

    public function test_manajer_only_sees_results_after_searching(): void
    {
        $this->seedMahasiswa('Budi Santoso', '2026001');
        $manajer = User::factory()->create(['role' => 'manajer', 'status' => 'aktif']);

        $kosong = $this->actingAs($manajer)->get('/poin');
        $kosong->assertOk();
        $kosong->assertViewIs('poin.manajer');
        $kosong->assertDontSee('Budi Santoso');

        $hasil = $this->actingAs($manajer)->get('/poin?q=2026001');
        $hasil->assertSee('Budi Santoso');
        $hasil->assertSee('2026001');
    }

    public function test_manajer_poin_input_asks_for_a_numeric_keyboard(): void
    {
        $this->seedMahasiswa('Budi', '2026001');
        $manajer = User::factory()->create(['role' => 'manajer', 'status' => 'aktif']);

        $this->actingAs($manajer)->get('/poin?q=2026001')
            ->assertSee('inputmode="numeric"', false);
    }

    public function test_only_admin_can_delete_a_violation(): void
    {
        $mahasiswa = $this->seedMahasiswa();
        $pelanggaran = Pelanggaran::create(['mahasiswa_id' => $mahasiswa->id, 'jenis' => 'Telat', 'poin' => 5]);

        $pj = User::factory()->create(['role' => 'pj', 'status' => 'aktif']);
        $manajer = User::factory()->create(['role' => 'manajer', 'status' => 'aktif']);

        $this->actingAs($pj)->delete("/pelanggaran/{$pelanggaran->id}")->assertForbidden();
        $this->actingAs($manajer)->delete("/pelanggaran/{$pelanggaran->id}")->assertForbidden();
        $this->assertSame(1, Pelanggaran::count());

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $this->actingAs($admin)->delete("/pelanggaran/{$pelanggaran->id}")->assertRedirect();
        $this->assertSame(0, Pelanggaran::count());
    }

    public function test_deleting_a_mahasiswa_cascades_to_their_violations(): void
    {
        $mahasiswa = $this->seedMahasiswa();
        Pelanggaran::create(['mahasiswa_id' => $mahasiswa->id, 'jenis' => 'Telat', 'poin' => 5]);

        $mahasiswa->forceDelete();

        $this->assertSame(0, Pelanggaran::count());
    }

    public function test_export_poin_contains_both_sheets_with_totals_and_sanksi(): void
    {
        $mahasiswa = $this->seedMahasiswa('Budi Santoso', '2026001');
        $this->beriAbsensi($mahasiswa, 'alpa'); // 5 poin absensi
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif', 'name' => 'Kesekre Satu']);
        Pelanggaran::create([
            'mahasiswa_id' => $mahasiswa->id, 'jenis' => 'Telat apel', 'poin' => 2, 'dicatat_oleh' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/export/poin');
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'poin').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);

        $this->assertSame(['Rekap Poin', 'Detail Pelanggaran'], $book->getSheetNames());

        $rekap = $book->getSheetByName('Rekap Poin');
        $this->assertSame('Total Poin', $rekap->getCell('G1')->getValue());
        $this->assertSame('Budi Santoso', $rekap->getCell('A2')->getValue());
        $this->assertEquals(5, $rekap->getCell('E2')->getValue());
        $this->assertEquals(2, $rekap->getCell('F2')->getValue());
        $this->assertEquals(7, $rekap->getCell('G2')->getValue());
        $this->assertStringContainsString('Edukasi Dijitalisasi', $rekap->getCell('H2')->getValue());

        $detail = $book->getSheetByName('Detail Pelanggaran');
        $this->assertSame('Telat apel', $detail->getCell('D2')->getValue());
        $this->assertEquals(2, $detail->getCell('E2')->getValue());
        $this->assertSame('Kesekre Satu', $detail->getCell('F2')->getValue());

        unlink($path);
    }
}
