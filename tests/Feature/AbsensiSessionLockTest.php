<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\SesiAbsensi;
use App\Models\SesiKelas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsensiSessionLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_is_locked_when_class_session_is_closed_and_unlocked_after_reopen(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $mahasiswa = Mahasiswa::create(['nama' => 'Made Putra', 'nim' => '23010001', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);
        $sesiKelas = SesiKelas::create(['sesi_absensi_id' => $sesi->id, 'kelas_id' => $kelas->id, 'status' => 'dibuka']);

        $pj = User::factory()->create(['role' => 'pj', 'status' => 'aktif']);
        $pj->kelas()->attach($kelas->id);

        // Open: PJ can record attendance.
        $this->actingAs($pj)
            ->put("/absensi/{$sesi->id}/{$kelas->id}", ['absensi' => [$mahasiswa->id => 'hadir']])
            ->assertRedirect();

        $this->assertDatabaseHas('absensi', [
            'mahasiswa_id' => $mahasiswa->id,
            'sesi_absensi_id' => $sesi->id,
            'status' => 'hadir',
        ]);

        // Closed: PJ can no longer change it.
        $sesiKelas->update(['status' => 'ditutup']);

        $this->actingAs($pj)
            ->put("/absensi/{$sesi->id}/{$kelas->id}", ['absensi' => [$mahasiswa->id => 'izin']])
            ->assertForbidden();

        $this->assertDatabaseHas('absensi', [
            'mahasiswa_id' => $mahasiswa->id,
            'sesi_absensi_id' => $sesi->id,
            'status' => 'hadir',
        ]);

        // Reopened: PJ can update again.
        $sesiKelas->update(['status' => 'dibuka']);

        $this->actingAs($pj)
            ->put("/absensi/{$sesi->id}/{$kelas->id}", ['absensi' => [$mahasiswa->id => 'izin']])
            ->assertRedirect();

        $this->assertDatabaseHas('absensi', [
            'mahasiswa_id' => $mahasiswa->id,
            'sesi_absensi_id' => $sesi->id,
            'status' => 'izin',
        ]);

        $this->assertSame(1, Absensi::count());
    }

    public function test_admin_can_still_edit_attendance_while_pj_stays_locked_out_on_a_closed_session(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $mahasiswa = Mahasiswa::create(['nama' => 'Made Putra', 'nim' => '23010001', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);
        SesiKelas::create(['sesi_absensi_id' => $sesi->id, 'kelas_id' => $kelas->id, 'status' => 'ditutup']);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $pj = User::factory()->create(['role' => 'pj', 'status' => 'aktif']);
        $pj->kelas()->attach($kelas->id);

        // PJ is still blocked on a closed session.
        $this->actingAs($pj)
            ->put("/absensi/{$sesi->id}/{$kelas->id}", ['absensi' => [$mahasiswa->id => 'hadir']])
            ->assertForbidden();

        // Admin can override and edit even though the session is closed.
        $this->actingAs($admin)
            ->put("/absensi/{$sesi->id}/{$kelas->id}", ['absensi' => [$mahasiswa->id => 'sakit']])
            ->assertRedirect();

        $this->assertDatabaseHas('absensi', [
            'mahasiswa_id' => $mahasiswa->id,
            'sesi_absensi_id' => $sesi->id,
            'status' => 'sakit',
            'recorded_by' => $admin->id,
        ]);
    }

    public function test_students_left_unselected_in_the_dropdown_are_skipped_not_errored(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $filled = Mahasiswa::create(['nama' => 'Diisi', 'nim' => '1', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $blank = Mahasiswa::create(['nama' => 'Belum Diisi', 'nim' => '2', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);
        SesiKelas::create(['sesi_absensi_id' => $sesi->id, 'kelas_id' => $kelas->id, 'status' => 'dibuka']);

        $pj = User::factory()->create(['role' => 'pj', 'status' => 'aktif']);
        $pj->kelas()->attach($kelas->id);

        $this->actingAs($pj)
            ->put("/absensi/{$sesi->id}/{$kelas->id}", [
                'absensi' => [$filled->id => 'hadir', $blank->id => ''],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('absensi', ['mahasiswa_id' => $filled->id, 'status' => 'hadir']);
        $this->assertDatabaseMissing('absensi', ['mahasiswa_id' => $blank->id]);
        $this->assertSame(1, Absensi::count());
    }
}
