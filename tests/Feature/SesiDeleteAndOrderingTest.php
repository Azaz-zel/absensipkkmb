<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\SesiAbsensi;
use App\Models\SesiKelas;
use App\Models\User;
use Database\Seeders\KelasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SesiDeleteAndOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_kelas_seeder_produces_the_configured_pagi_and_malam_ranges(): void
    {
        (new KelasSeeder())->run();

        $this->assertSame(23, Kelas::where('kategori', 'pagi')->count());
        $this->assertSame(9, Kelas::where('kategori', 'malam')->count());
        $this->assertTrue(Kelas::where('nama_kelas', 'W')->exists());
        $this->assertFalse(Kelas::where('nama_kelas', 'X')->exists());
        $this->assertTrue(Kelas::where('nama_kelas', 'AI')->exists());
        $this->assertFalse(Kelas::where('nama_kelas', 'AJ')->exists());
    }

    public function test_admin_can_delete_a_sesi_and_its_related_records_cascade(): void
    {
        (new KelasSeeder())->run();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $kelas = Kelas::where('nama_kelas', 'A')->first();
        $mahasiswa = Mahasiswa::create(['nama' => 'Made Putra', 'nim' => '23010001', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now(), 'created_by' => $admin->id]);
        SesiKelas::create(['sesi_absensi_id' => $sesi->id, 'kelas_id' => $kelas->id, 'status' => 'dibuka']);
        Absensi::create([
            'mahasiswa_id' => $mahasiswa->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi->id,
            'status' => 'hadir', 'recorded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete("/sesi/{$sesi->id}")
            ->assertRedirect('/sesi');

        $this->assertDatabaseMissing('sesi_absensi', ['id' => $sesi->id]);
        $this->assertDatabaseMissing('sesi_kelas', ['sesi_absensi_id' => $sesi->id]);
        $this->assertDatabaseMissing('absensi', ['sesi_absensi_id' => $sesi->id]);
        // The student record itself must survive deleting the session.
        $this->assertDatabaseHas('mahasiswa', ['id' => $mahasiswa->id]);
    }

    public function test_pj_cannot_delete_a_sesi(): void
    {
        (new KelasSeeder())->run();

        $pj = User::factory()->create(['role' => 'pj', 'status' => 'aktif']);
        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);

        $this->actingAs($pj)->delete("/sesi/{$sesi->id}")->assertForbidden();
        $this->assertDatabaseHas('sesi_absensi', ['id' => $sesi->id]);
    }

    public function test_kelas_pagi_is_listed_before_kelas_malam(): void
    {
        (new KelasSeeder())->run();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/mahasiswa/create');

        $response->assertOk();

        $html = $response->getContent();
        $posPagiFirst = strpos($html, '(pagi)');
        $posMalamFirst = strpos($html, '(malam)');

        $this->assertNotFalse($posPagiFirst);
        $this->assertNotFalse($posMalamFirst);
        $this->assertLessThan($posMalamFirst, $posPagiFirst, 'Expected kelas pagi options to render before kelas malam options.');
    }
}
