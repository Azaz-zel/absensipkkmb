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

    public function test_admin_can_delete_only_the_checked_students(): void
    {
        [$kelasA, $kelasB] = $this->seedKelasWithStudents();
        $dipilih = Mahasiswa::where('kelas_id', $kelasA->id)->orderBy('id')->take(2)->pluck('id')->all();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)
            ->delete('/mahasiswa/massal/terpilih', ['ids' => $dipilih])
            ->assertRedirect('/mahasiswa');

        $this->assertSame(1, Mahasiswa::where('kelas_id', $kelasA->id)->count());
        $this->assertSame(2, Mahasiswa::where('kelas_id', $kelasB->id)->count());
        $this->assertSame(2, Mahasiswa::onlyTrashed()->count());
    }

    public function test_delete_selected_needs_at_least_one_id(): void
    {
        $this->seedKelasWithStudents();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)
            ->delete('/mahasiswa/massal/terpilih', ['ids' => []])
            ->assertSessionHasErrors('ids');

        $this->assertSame(5, Mahasiswa::count());
    }

    public function test_non_admin_cannot_delete_selected_students(): void
    {
        $this->seedKelasWithStudents();
        $ids = Mahasiswa::pluck('id')->all();
        $pj = User::factory()->create(['role' => 'pj', 'status' => 'aktif']);

        $this->actingAs($pj)->delete('/mahasiswa/massal/terpilih', ['ids' => $ids])->assertForbidden();

        $this->assertSame(5, Mahasiswa::count());
    }

    public function test_mahasiswa_page_uses_a_checklist_instead_of_the_danger_zone_card(): void
    {
        $this->seedKelasWithStudents();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/mahasiswa');

        $response->assertSee('Pilih Mahasiswa');
        $response->assertSee('name="ids[]"', false);
        $response->assertSee('Hapus Terpilih');
        $response->assertSee('Hapus Semua Data');
        $response->assertDontSee('Zona Berbahaya');
    }

    public function test_row_actions_stay_hidden_until_the_checklist_is_opened(): void
    {
        $this->seedKelasWithStudents();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/mahasiswa');

        // Kolom Aksi dirender tapi ber-class hidden; JS yang membukanya saat mode pilih nyala.
        $response->assertSee('kolom-aksi-maba hidden', false);
        $response->assertSee('baris-maba', false);
        $response->assertSee('data-abaikan-pilih', false);
    }
}
