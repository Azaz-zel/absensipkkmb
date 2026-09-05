<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\Pelanggaran;
use App\Models\SesiAbsensi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapAndDashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private function seedTwoClassesWithAttendance(): array
    {
        $kelasA = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $kelasB = Kelas::create(['nama_kelas' => 'B', 'kategori' => 'pagi', 'status' => 'aktif']);

        $a1 = Mahasiswa::create(['nama' => 'A1', 'nim' => 'A1', 'jurusan' => 'TI', 'kelas_id' => $kelasA->id]);
        $a2 = Mahasiswa::create(['nama' => 'A2', 'nim' => 'A2', 'jurusan' => 'TI', 'kelas_id' => $kelasA->id]);
        $b1 = Mahasiswa::create(['nama' => 'B1', 'nim' => 'B1', 'jurusan' => 'TI', 'kelas_id' => $kelasB->id]);

        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);

        // Kelas A: 1 hadir, 1 sakit. Kelas B: 1 izin.
        Absensi::create(['mahasiswa_id' => $a1->id, 'kelas_id' => $kelasA->id, 'sesi_absensi_id' => $sesi->id, 'status' => 'hadir']);
        Absensi::create(['mahasiswa_id' => $a2->id, 'kelas_id' => $kelasA->id, 'sesi_absensi_id' => $sesi->id, 'status' => 'sakit']);
        Absensi::create(['mahasiswa_id' => $b1->id, 'kelas_id' => $kelasB->id, 'sesi_absensi_id' => $sesi->id, 'status' => 'izin']);

        return [$kelasA, $kelasB];
    }

    public function test_dashboard_shows_total_kehadiran_and_tidak_hadir_across_everything(): void
    {
        $this->seedTwoClassesWithAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $response->assertSee('Total Kehadiran');
        $response->assertViewHas('stats', fn ($stats) => $stats['total_kehadiran'] === 1 && $stats['tidak_hadir'] === 2);
    }

    public function test_rekap_mahasiswa_totals_reflect_kelas_filter(): void
    {
        [$kelasA] = $this->seedTwoClassesWithAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        // Unfiltered: grand total across both classes.
        $this->actingAs($admin)->get('/rekap/mahasiswa')
            ->assertViewHas('totalHadir', 1)
            ->assertViewHas('totalTidakHadir', 2);

        // Filtered to kelas A only: 1 hadir, 1 sakit (tidak hadir), kelas B excluded.
        $this->actingAs($admin)->get("/rekap/mahasiswa?kelas_id={$kelasA->id}")
            ->assertViewHas('totalHadir', 1)
            ->assertViewHas('totalTidakHadir', 1);
    }

    public function test_rekap_kelas_totals_reflect_kelas_filter(): void
    {
        [$kelasA] = $this->seedTwoClassesWithAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)->get('/rekap/kelas')
            ->assertViewHas('totalHadir', 1)
            ->assertViewHas('totalTidakHadir', 2);

        $this->actingAs($admin)->get("/rekap/kelas?kelas_id={$kelasA->id}")
            ->assertViewHas('totalHadir', 1)
            ->assertViewHas('totalTidakHadir', 1);
    }

    public function test_admin_dashboard_shows_the_points_panels_too(): void
    {
        [$kelasA] = $this->seedTwoClassesWithAttendance();
        $kena = Mahasiswa::where('nim', 'A1')->firstOrFail();
        Pelanggaran::create(['mahasiswa_id' => $kena->id, 'jenis' => 'Telat apel', 'poin' => 11]);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $response->assertSee('Sebaran Sanksi');
        $response->assertSee('Pelanggaran Terbaru');
        $response->assertSee('Poin Tertinggi');
        $response->assertSee('Telat apel');

        // A1 kena 11 poin pelanggaran, A2 sakit (3) dan B1 izin (3) juga kena poin absensi.
        $this->assertSame(3, $response->viewData('stats')['kena_poin']);
        $this->assertSame(1, $response->viewData('stats')['perlu_sanksi_berat']);
        $this->assertSame(1, $response->viewData('sebaran')['berat']);
        $this->assertSame(2, $response->viewData('sebaran')['ringan']);
        $this->assertSame($kena->id, $response->viewData('topPoin')->first()->id);
    }

    public function test_admin_dashboard_reports_progress_of_the_latest_sesi(): void
    {
        $this->seedTwoClassesWithAttendance();
        // Satu maba tambahan yang belum diabsen sama sekali.
        $kelas = Kelas::where('nama_kelas', 'A')->firstOrFail();
        Mahasiswa::create(['nama' => 'A3', 'nim' => 'A3', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $response = $this->actingAs($admin)->get('/');

        $progres = $response->viewData('progresSesi');

        $this->assertSame(1, $progres['hadir']);
        $this->assertSame(1, $progres['sakit']);
        $this->assertSame(1, $progres['izin']);
        $this->assertSame(0, $progres['alpa']);
        $this->assertSame(3, $progres['sudah_diabsen']);
        $this->assertSame(1, $progres['belum_diabsen']);
        $this->assertSame(4, $progres['total_maba']);

        $response->assertSee('Progres Sesi 1');
        $response->assertSee('Belum Diabsen');
    }

    public function test_admin_dashboard_hides_the_progress_panel_when_there_is_no_sesi(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $this->assertNull($response->viewData('progresSesi'));
        $response->assertSee('Belum ada sesi absensi.');
        $response->assertSee('Semua masih bersih.');
    }

    public function test_admin_dashboard_counts_active_pj_accounts(): void
    {
        User::factory()->create(['role' => 'pj', 'status' => 'aktif']);
        User::factory()->create(['role' => 'pj', 'status' => 'nonaktif']);
        User::factory()->create(['role' => 'manajer', 'status' => 'aktif']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/');

        $this->assertSame(1, $response->viewData('stats')['total_pj']);
    }

    public function test_recap_reports_how_many_students_are_still_unmarked(): void
    {
        // 3 maba, 1 sesi, baru 3 keterangan terisi -> lengkap.
        $this->seedTwoClassesWithAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/rekap/mahasiswa');
        $ringkasan = $response->viewData('ringkasan');

        $this->assertSame(3, $ringkasan['total_maba']);
        $this->assertSame(1, $ringkasan['jumlah_sesi']);
        $this->assertSame(3, $ringkasan['seharusnya']);
        $this->assertSame(3, $ringkasan['sudah_diabsen']);
        $this->assertSame(0, $ringkasan['belum_diabsen']);
        $this->assertTrue($ringkasan['lengkap']);
        $response->assertSee('Semua sudah diabsen');
    }

    public function test_recap_flags_students_that_have_not_been_marked_yet(): void
    {
        [$kelasA] = $this->seedTwoClassesWithAttendance();
        Mahasiswa::create(['nama' => 'A3', 'nim' => 'A3', 'jurusan' => 'TI', 'kelas_id' => $kelasA->id]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/rekap/mahasiswa');
        $ringkasan = $response->viewData('ringkasan');

        $this->assertSame(4, $ringkasan['seharusnya']);
        $this->assertSame(3, $ringkasan['sudah_diabsen']);
        $this->assertSame(1, $ringkasan['belum_diabsen']);
        $this->assertFalse($ringkasan['lengkap']);
        $response->assertSee('1 belum diabsen');
    }

    public function test_recap_expectation_multiplies_by_the_number_of_sesi(): void
    {
        $this->seedTwoClassesWithAttendance();
        SesiAbsensi::create(['nama_sesi' => 'Sesi 2', 'tanggal' => now()->addDay()]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $semua = $this->actingAs($admin)->get('/rekap/mahasiswa')->viewData('ringkasan');
        $this->assertSame(2, $semua['jumlah_sesi']);
        $this->assertSame(6, $semua['seharusnya']);
        $this->assertSame(3, $semua['belum_diabsen']);

        // Difilter ke satu sesi, patokannya kembali ke jumlah maba saja.
        $sesi1 = SesiAbsensi::where('nama_sesi', 'Sesi 1')->firstOrFail();
        $satu = $this->actingAs($admin)->get("/rekap/mahasiswa?sesi={$sesi1->id}")->viewData('ringkasan');
        $this->assertSame(1, $satu['jumlah_sesi']);
        $this->assertSame(3, $satu['seharusnya']);
        $this->assertSame(0, $satu['belum_diabsen']);
    }

    public function test_recap_per_kelas_shows_column_totals_and_unmarked_count(): void
    {
        [$kelasA, $kelasB] = $this->seedTwoClassesWithAttendance();
        Mahasiswa::create(['nama' => 'B2', 'nim' => 'B2', 'jurusan' => 'TI', 'kelas_id' => $kelasB->id]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/rekap/kelas');

        $this->assertSame(1, $response->viewData('totalHadir'));
        $this->assertSame(1, $response->viewData('totalIzin'));
        $this->assertSame(1, $response->viewData('totalSakit'));
        $this->assertSame(0, $response->viewData('totalAlpa'));
        $this->assertSame(4, $response->viewData('totalMaba'));
        $this->assertSame(1, $response->viewData('ringkasan')['belum_diabsen']);

        $perKelas = $response->viewData('rekap')->keyBy(fn ($row) => $row['kelas']->id);
        $this->assertSame(0, $perKelas[$kelasA->id]['belum']);
        $this->assertSame(1, $perKelas[$kelasB->id]['belum']);

        $response->assertSee('Belum');
        $response->assertSee('Total 2 kelas');
    }
}
