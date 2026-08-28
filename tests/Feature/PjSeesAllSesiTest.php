<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\SesiAbsensi;
use App\Models\SesiKelas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PjSeesAllSesiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pj_sees_every_sesi_at_once_without_picking_one_first(): void
    {
        $kelasA = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $day1 = SesiAbsensi::create(['nama_sesi' => 'Pra PKKMB DAY 1', 'tanggal' => '2026-09-04']);
        $day2 = SesiAbsensi::create(['nama_sesi' => 'Pra PKKMB DAY 2', 'tanggal' => '2026-09-05']);
        SesiKelas::create(['sesi_absensi_id' => $day1->id, 'kelas_id' => $kelasA->id, 'status' => 'dibuka']);
        SesiKelas::create(['sesi_absensi_id' => $day2->id, 'kelas_id' => $kelasA->id, 'status' => 'belum_dibuka']);

        $pj = User::factory()->create(['role' => 'pj', 'status' => 'aktif']);
        $pj->kelas()->attach($kelasA->id);

        $response = $this->actingAs($pj)->get('/absensi');

        $response->assertOk();
        $response->assertSee('Pra PKKMB DAY 1');
        $response->assertSee('Pra PKKMB DAY 2');
        // No "pick a sesi first" dropdown gate for PJ.
        $response->assertDontSee('Pilih Sesi');
        $response->assertDontSee('Pilih sesi absensi terlebih dahulu');
    }

    public function test_admin_still_gets_the_sesi_dropdown(): void
    {
        SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)->get('/absensi')->assertOk()->assertSee('Pilih Sesi');
    }
}
