<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\SesiAbsensi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsensiRecordEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_edit_and_delete_a_single_attendance_record(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $mahasiswa = Mahasiswa::create(['nama' => 'Made Putra', 'nim' => '23010001', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);
        $absensi = Absensi::create([
            'mahasiswa_id' => $mahasiswa->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi->id, 'status' => 'hadir',
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)
            ->get("/rekap/mahasiswa/{$mahasiswa->id}")
            ->assertOk()
            ->assertSee($mahasiswa->nama)
            ->assertSee('Sesi 1');

        // Simulates the browser submitting the edit form from the detail page with a
        // filter still in its query string — the redirect (via back()) must land there again.
        $originUrl = "/rekap/mahasiswa/{$mahasiswa->id}?kelas_id=".$kelas->id;

        $this->actingAs($admin)
            ->from($originUrl)
            ->put("/absensi-record/{$absensi->id}", ['status' => 'sakit'])
            ->assertRedirect($originUrl);

        $this->assertDatabaseHas('absensi', ['id' => $absensi->id, 'status' => 'sakit']);

        $this->actingAs($admin)
            ->from($originUrl)
            ->delete("/absensi-record/{$absensi->id}")
            ->assertRedirect($originUrl);

        $this->assertDatabaseMissing('absensi', ['id' => $absensi->id]);
        // Deleting the attendance record must never touch the student record.
        $this->assertDatabaseHas('mahasiswa', ['id' => $mahasiswa->id]);
    }

    public function test_pj_can_view_but_not_edit_or_delete_attendance_records(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $mahasiswa = Mahasiswa::create(['nama' => 'Made Putra', 'nim' => '23010001', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);
        $absensi = Absensi::create([
            'mahasiswa_id' => $mahasiswa->id, 'kelas_id' => $kelas->id, 'sesi_absensi_id' => $sesi->id, 'status' => 'hadir',
        ]);

        $pj = User::factory()->create(['role' => 'pj', 'status' => 'aktif']);
        $pj->kelas()->attach($kelas->id);

        $this->actingAs($pj)->get("/rekap/mahasiswa/{$mahasiswa->id}")->assertOk();
        $this->actingAs($pj)->put("/absensi-record/{$absensi->id}", ['status' => 'sakit'])->assertForbidden();
        $this->actingAs($pj)->delete("/absensi-record/{$absensi->id}")->assertForbidden();

        $this->assertDatabaseHas('absensi', ['id' => $absensi->id, 'status' => 'hadir']);
    }

    public function test_rekap_filter_is_preserved_through_the_detail_link_and_back_link(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $mahasiswa = Mahasiswa::create(['nama' => 'Made Putra', 'nim' => '23010001', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        // The listing, filtered by kelas, must link to the detail page carrying that filter.
        $list = $this->actingAs($admin)->get("/rekap/mahasiswa?kelas_id={$kelas->id}");
        $list->assertOk();
        $list->assertSee("/rekap/mahasiswa/{$mahasiswa->id}?kelas_id={$kelas->id}", escape: false);

        // The detail page itself, opened with that filter in its URL, must link back to the
        // filtered listing rather than resetting to the unfiltered one.
        $detail = $this->actingAs($admin)->get("/rekap/mahasiswa/{$mahasiswa->id}?kelas_id={$kelas->id}");
        $detail->assertOk();
        $detail->assertSee("/rekap/mahasiswa?kelas_id={$kelas->id}", escape: false);
    }

    public function test_pj_cannot_view_attendance_detail_for_a_student_outside_their_kelas(): void
    {
        $kelasA = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $kelasB = Kelas::create(['nama_kelas' => 'B', 'kategori' => 'pagi', 'status' => 'aktif']);
        $mahasiswaB = Mahasiswa::create(['nama' => 'Komang Arya', 'nim' => '23010002', 'jurusan' => 'TI', 'kelas_id' => $kelasB->id]);

        $pj = User::factory()->create(['role' => 'pj', 'status' => 'aktif']);
        $pj->kelas()->attach($kelasA->id);

        $this->actingAs($pj)->get("/rekap/mahasiswa/{$mahasiswaB->id}")->assertForbidden();
    }
}
