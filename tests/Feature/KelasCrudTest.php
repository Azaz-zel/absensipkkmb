<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\SesiAbsensi;
use App\Models\SesiKelas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KelasCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_pagi_kelas(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)
            ->post('/kelas', ['nama_kelas' => 'z', 'kategori' => 'pagi', 'kapasitas' => 30])
            ->assertRedirect('/kelas');

        // Lowercase input is normalized to uppercase.
        $this->assertDatabaseHas('kelas', ['nama_kelas' => 'Z', 'kategori' => 'pagi', 'kapasitas' => 30]);
    }

    public function test_kelas_name_format_is_validated_per_kategori(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        // Two letters is invalid for a pagi kelas.
        $this->actingAs($admin)
            ->post('/kelas', ['nama_kelas' => 'ZZ', 'kategori' => 'pagi'])
            ->assertSessionHasErrors('nama_kelas');

        // A single letter is invalid for a malam kelas.
        $this->actingAs($admin)
            ->post('/kelas', ['nama_kelas' => 'Z', 'kategori' => 'malam'])
            ->assertSessionHasErrors('nama_kelas');

        $this->assertSame(0, Kelas::count());
    }

    public function test_creating_a_kelas_backfills_sesi_kelas_rows_for_existing_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);

        $this->actingAs($admin)->post('/kelas', ['nama_kelas' => 'Z', 'kategori' => 'pagi']);

        $kelas = Kelas::where('nama_kelas', 'Z')->first();

        $this->assertDatabaseHas('sesi_kelas', [
            'sesi_absensi_id' => $sesi->id,
            'kelas_id' => $kelas->id,
            'status' => 'belum_dibuka',
        ]);

        // And the admin can immediately open it for that already-existing session.
        $this->actingAs($admin)
            ->post("/sesi/{$sesi->id}/{$kelas->id}/buka")
            ->assertRedirect();

        $this->assertSame('dibuka', SesiKelas::where('sesi_absensi_id', $sesi->id)->where('kelas_id', $kelas->id)->first()->status);
    }

    public function test_admin_can_edit_a_kelas(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'Z', 'kategori' => 'pagi', 'status' => 'aktif']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)
            ->put("/kelas/{$kelas->id}", ['nama_kelas' => 'Z', 'kategori' => 'pagi', 'kapasitas' => 40])
            ->assertRedirect('/kelas');

        $this->assertDatabaseHas('kelas', ['id' => $kelas->id, 'kapasitas' => 40]);
    }

    public function test_admin_can_delete_an_empty_kelas(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'Z', 'kategori' => 'pagi', 'status' => 'aktif']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)
            ->delete("/kelas/{$kelas->id}")
            ->assertRedirect('/kelas');

        $this->assertDatabaseMissing('kelas', ['id' => $kelas->id]);
    }

    public function test_kelas_with_students_cannot_be_deleted_and_gets_a_clear_error(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'Z', 'kategori' => 'pagi', 'status' => 'aktif']);
        Mahasiswa::create(['nama' => 'Made Putra', 'nim' => '1', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->delete("/kelas/{$kelas->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('kelas', ['id' => $kelas->id]);
    }

    public function test_kelas_with_only_soft_deleted_students_still_cannot_be_deleted(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'Z', 'kategori' => 'pagi', 'status' => 'aktif']);
        $mahasiswa = Mahasiswa::create(['nama' => 'Made Putra', 'nim' => '1', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $mahasiswa->delete();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->delete("/kelas/{$kelas->id}");

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('kelas', ['id' => $kelas->id]);
    }

    public function test_pj_cannot_manage_kelas(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'Z', 'kategori' => 'pagi', 'status' => 'aktif']);
        $pj = User::factory()->create(['role' => 'pj', 'status' => 'aktif']);

        $this->actingAs($pj)->get('/kelas')->assertForbidden();
        $this->actingAs($pj)->post('/kelas', ['nama_kelas' => 'Y', 'kategori' => 'pagi'])->assertForbidden();
        $this->actingAs($pj)->delete("/kelas/{$kelas->id}")->assertForbidden();
    }
}
