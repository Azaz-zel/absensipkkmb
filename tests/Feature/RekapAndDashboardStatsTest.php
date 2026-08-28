<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Mahasiswa;
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
}
