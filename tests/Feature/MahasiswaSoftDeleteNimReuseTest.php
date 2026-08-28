<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MahasiswaSoftDeleteNimReuseTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_add_allows_reusing_a_soft_deleted_nim(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $old = Mahasiswa::create(['nama' => 'Old', 'nim' => '44440001', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $old->delete();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)->post('/mahasiswa', [
            'nama' => 'New Person', 'nim' => '44440001', 'jurusan' => 'TI', 'kelas_id' => $kelas->id,
        ])->assertRedirect('/mahasiswa')->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Mahasiswa::where('nim', '44440001')->count());
        $this->assertSame('New Person', Mahasiswa::where('nim', '44440001')->first()->nama);
    }

    public function test_two_active_students_still_cannot_share_the_same_nim(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        Mahasiswa::create(['nama' => 'Existing', 'nim' => '44440002', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)->post('/mahasiswa', [
            'nama' => 'Duplicate', 'nim' => '44440002', 'jurusan' => 'TI', 'kelas_id' => $kelas->id,
        ])->assertSessionHasErrors('nim');

        $this->assertSame(1, Mahasiswa::where('nim', '44440002')->count());
    }
}
