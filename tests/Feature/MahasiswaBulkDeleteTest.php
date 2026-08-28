<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MahasiswaBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function seedKelasWithStudents(): array
    {
        $kelasA = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $kelasB = Kelas::create(['nama_kelas' => 'B', 'kategori' => 'pagi', 'status' => 'aktif']);

        foreach (range(1, 3) as $i) {
            Mahasiswa::create(['nama' => "A{$i}", 'nim' => "A{$i}0001", 'jurusan' => 'TI', 'kelas_id' => $kelasA->id]);
        }
        foreach (range(1, 2) as $i) {
            Mahasiswa::create(['nama' => "B{$i}", 'nim' => "B{$i}0001", 'jurusan' => 'TI', 'kelas_id' => $kelasB->id]);
        }

        return [$kelasA, $kelasB];
    }

    public function test_admin_can_delete_all_mahasiswa_in_one_kelas_without_touching_others(): void
    {
        [$kelasA, $kelasB] = $this->seedKelasWithStudents();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)
            ->delete("/mahasiswa/massal/kelas/{$kelasA->id}")
            ->assertRedirect('/mahasiswa');

        $this->assertSame(0, Mahasiswa::where('kelas_id', $kelasA->id)->count());
        $this->assertSame(2, Mahasiswa::where('kelas_id', $kelasB->id)->count());

        // Soft delete, not a hard delete — RPD requires recoverability.
        $this->assertSame(3, Mahasiswa::onlyTrashed()->where('kelas_id', $kelasA->id)->count());
    }

    public function test_admin_can_delete_all_mahasiswa_across_every_kelas(): void
    {
        $this->seedKelasWithStudents();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)
            ->delete('/mahasiswa/massal/semua')
            ->assertRedirect('/mahasiswa');

        $this->assertSame(0, Mahasiswa::count());
        $this->assertSame(5, Mahasiswa::onlyTrashed()->count());
    }

    public function test_pj_cannot_bulk_delete_mahasiswa(): void
    {
        [$kelasA] = $this->seedKelasWithStudents();
        $pj = User::factory()->create(['role' => 'pj', 'status' => 'aktif']);

        $this->actingAs($pj)->delete("/mahasiswa/massal/kelas/{$kelasA->id}")->assertForbidden();
        $this->actingAs($pj)->delete('/mahasiswa/massal/semua')->assertForbidden();

        $this->assertSame(5, Mahasiswa::count());
    }
}
