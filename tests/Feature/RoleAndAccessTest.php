<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\SesiAbsensi;
use App\Models\SesiKelas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAndAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_pj_cannot_access_admin_only_route(): void
    {
        $pj = User::factory()->create(['role' => 'pj', 'status' => 'aktif']);

        $this->actingAs($pj)->get('/mahasiswa')->assertForbidden();
    }

    public function test_admin_can_access_admin_only_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)->get('/mahasiswa')->assertOk();
    }

    public function test_pj_is_blocked_from_unassigned_kelas_but_allowed_on_assigned_kelas(): void
    {
        $kelasA = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $kelasB = Kelas::create(['nama_kelas' => 'B', 'kategori' => 'pagi', 'status' => 'aktif']);

        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);
        SesiKelas::create(['sesi_absensi_id' => $sesi->id, 'kelas_id' => $kelasA->id, 'status' => 'dibuka']);
        SesiKelas::create(['sesi_absensi_id' => $sesi->id, 'kelas_id' => $kelasB->id, 'status' => 'dibuka']);

        $pj = User::factory()->create(['role' => 'pj', 'status' => 'aktif']);
        $pj->kelas()->attach($kelasA->id);

        $this->actingAs($pj)->get("/absensi/{$sesi->id}/{$kelasA->id}")->assertOk();
        $this->actingAs($pj)->get("/absensi/{$sesi->id}/{$kelasB->id}")->assertForbidden();
    }

    public function test_admin_bypasses_class_access_restriction(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);
        SesiKelas::create(['sesi_absensi_id' => $sesi->id, 'kelas_id' => $kelas->id, 'status' => 'dibuka']);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)->get("/absensi/{$sesi->id}/{$kelas->id}")->assertOk();
    }
}
