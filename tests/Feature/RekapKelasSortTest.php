<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\SesiAbsensi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapKelasSortTest extends TestCase
{
    use RefreshDatabase;

    private function seedThreeClassesWithDifferentAttendance(): void
    {
        // Kelas order alphabetically: A, B, C — but attendance from worst to best: C, A, B.
        $kelasA = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $kelasB = Kelas::create(['nama_kelas' => 'B', 'kategori' => 'pagi', 'status' => 'aktif']);
        $kelasC = Kelas::create(['nama_kelas' => 'C', 'kategori' => 'pagi', 'status' => 'aktif']);

        $a = Mahasiswa::create(['nama' => 'A1', 'nim' => 'a1', 'jurusan' => 'TI', 'kelas_id' => $kelasA->id]);
        $b = Mahasiswa::create(['nama' => 'B1', 'nim' => 'b1', 'jurusan' => 'TI', 'kelas_id' => $kelasB->id]);
        $c = Mahasiswa::create(['nama' => 'C1', 'nim' => 'c1', 'jurusan' => 'TI', 'kelas_id' => $kelasC->id]);

        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);

        Absensi::create(['mahasiswa_id' => $a->id, 'kelas_id' => $kelasA->id, 'sesi_absensi_id' => $sesi->id, 'status' => 'izin']); // 0%
        Absensi::create(['mahasiswa_id' => $b->id, 'kelas_id' => $kelasB->id, 'sesi_absensi_id' => $sesi->id, 'status' => 'hadir']); // 100%
        Absensi::create(['mahasiswa_id' => $c->id, 'kelas_id' => $kelasC->id, 'sesi_absensi_id' => $sesi->id, 'status' => 'alpa']); // 0%
    }

    public function test_default_sort_is_kelas_alphabetical(): void
    {
        $this->seedThreeClassesWithDifferentAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/rekap/kelas');

        $names = $response->viewData('rekap')->pluck('kelas.nama_kelas')->values()->all();
        $this->assertSame(['A', 'B', 'C'], $names);
    }

    public function test_sort_by_percentage_descending(): void
    {
        $this->seedThreeClassesWithDifferentAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/rekap/kelas?sort=persen_desc');

        $names = $response->viewData('rekap')->pluck('kelas.nama_kelas')->values()->all();
        // B (100%) must come first; A and C (both 0%) follow in some order.
        $this->assertSame('B', $names[0]);
    }

    public function test_sort_by_percentage_ascending(): void
    {
        $this->seedThreeClassesWithDifferentAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/rekap/kelas?sort=persen_asc');

        $names = $response->viewData('rekap')->pluck('kelas.nama_kelas')->values()->all();
        // B (100%) must come last.
        $this->assertSame('B', end($names));
    }

    public function test_active_filter_badges_show_on_the_page_when_filters_applied(): void
    {
        $this->seedThreeClassesWithDifferentAttendance();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $noFilter = $this->actingAs($admin)->get('/rekap/kelas');
        $noFilter->assertDontSee('Filter aktif');

        $kelasA = Kelas::where('nama_kelas', 'A')->firstOrFail();
        $withFilter = $this->actingAs($admin)->get("/rekap/kelas?kelas_id={$kelasA->id}&kategori=pagi");
        $withFilter->assertSee('Filter aktif');
        $withFilter->assertSee('Kelas: A', false);
        $withFilter->assertSee('Kategori: Pagi', false);
    }
}
