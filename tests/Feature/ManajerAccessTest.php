<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\Pelanggaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManajerAccessTest extends TestCase
{
    use RefreshDatabase;

    private function manajer(): User
    {
        return User::factory()->create(['role' => 'manajer', 'status' => 'aktif']);
    }

    public function test_manajer_can_open_dashboard_and_poin(): void
    {
        $manajer = $this->manajer();

        $this->actingAs($manajer)->get('/')->assertOk()->assertViewIs('dashboard.manajer');
        $this->actingAs($manajer)->get('/poin')->assertOk()->assertViewIs('poin.manajer');
    }

    public function test_manajer_dashboard_is_about_points_not_sessions(): void
    {
        $response = $this->actingAs($this->manajer())->get('/');

        $response->assertSee('Cari Maba, Catat Poin');
        $response->assertSee('Sebaran Sanksi');
        $response->assertSee('Pelanggaran Terbaru');
        $response->assertSee('Poin Tertinggi');

        $response->assertDontSee('Sesi Terbaru');
        $response->assertDontSee('Sesi Aktif');
    }

    public function test_manajer_dashboard_summarises_the_sanksi_spread(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $bersih = Mahasiswa::create(['nama' => 'Ani', 'nim' => '2026001', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $kena = Mahasiswa::create(['nama' => 'Budi', 'nim' => '2026002', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        Pelanggaran::create(['mahasiswa_id' => $kena->id, 'jenis' => 'Telat apel', 'poin' => 12]);

        $response = $this->actingAs($this->manajer())->get('/');

        $this->assertSame(2, $response->viewData('stats')['total_maba']);
        $this->assertSame(1, $response->viewData('stats')['kena_poin']);
        $this->assertSame(1, $response->viewData('stats')['aman']);
        $this->assertSame(1, $response->viewData('stats')['perlu_sanksi_berat']);
        $this->assertSame(12, $response->viewData('stats')['total_poin_pelanggaran']);

        $sebaran = $response->viewData('sebaran');
        $this->assertSame(1, $sebaran['aman']);
        $this->assertSame(1, $sebaran['berat']);

        // Cuma maba yang kena poin yang muncul di tabel poin tertinggi.
        $this->assertSame([$kena->id], $response->viewData('topPoin')->pluck('id')->all());
        $response->assertSee('Budi');
        $response->assertDontSee($bersih->nim);
    }

    public function test_manajer_dashboard_lists_the_latest_violations(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $mahasiswa = Mahasiswa::create(['nama' => 'Budi', 'nim' => '2026001', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        $manajer = $this->manajer();
        Pelanggaran::create([
            'mahasiswa_id' => $mahasiswa->id, 'jenis' => 'Atribut kurang', 'poin' => 3, 'dicatat_oleh' => $manajer->id,
        ]);

        $response = $this->actingAs($manajer)->get('/');

        $response->assertSee('Atribut kurang');
        $response->assertSee('+3');
        $response->assertSee($manajer->name);
        $this->assertSame(1, $response->viewData('stats')['pelanggaran_hari_ini']);
    }

    public function test_manajer_dashboard_still_renders_with_no_data_at_all(): void
    {
        $response = $this->actingAs($this->manajer())->get('/');

        $response->assertOk();
        $response->assertSee('Belum ada pelanggaran yang dicatat.');
        $response->assertSee('Semua masih bersih.');
    }

    public function test_manajer_is_blocked_from_absensi_rekap_and_admin_pages(): void
    {
        $manajer = $this->manajer();

        foreach (['/absensi', '/rekap/mahasiswa', '/rekap/kelas', '/kelas', '/sesi', '/mahasiswa', '/import', '/users'] as $url) {
            $this->actingAs($manajer)->get($url)->assertForbidden();
        }
    }

    public function test_manajer_cannot_export_or_bulk_delete(): void
    {
        $manajer = $this->manajer();

        $this->actingAs($manajer)->get('/export/rekap')->assertForbidden();
        $this->actingAs($manajer)->get('/export/poin')->assertForbidden();
        $this->actingAs($manajer)->delete('/mahasiswa/massal/semua')->assertForbidden();
    }

    public function test_manajer_sidebar_only_offers_dashboard_and_poin(): void
    {
        $response = $this->actingAs($this->manajer())->get('/');

        $response->assertSee('Poin Mahasiswa');
        $response->assertDontSee('Rekap Kehadiran');
        $response->assertDontSee('Kelola Kelas');
        $response->assertDontSee('Akun Pengguna');
    }

    public function test_pj_cannot_reach_the_poin_page(): void
    {
        $pj = User::factory()->create(['role' => 'pj', 'status' => 'aktif']);

        $this->actingAs($pj)->get('/poin')->assertForbidden();
    }

    public function test_pj_and_admin_keep_their_access_to_absensi_and_rekap(): void
    {
        $pj = User::factory()->create(['role' => 'pj', 'status' => 'aktif']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($pj)->get('/absensi')->assertOk();
        $this->actingAs($pj)->get('/rekap/mahasiswa')->assertOk();
        $this->actingAs($admin)->get('/absensi')->assertOk();
        $this->actingAs($admin)->get('/poin')->assertOk();
    }

    public function test_manajer_role_can_be_assigned_from_the_user_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)->post('/users', [
            'name' => 'Kesekre Satu',
            'email' => 'kesekre@pkkmb.test',
            'username' => 'kesekre',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'role' => 'manajer',
            'status' => 'aktif',
        ])->assertRedirect();

        $this->assertSame('manajer', User::where('email', 'kesekre@pkkmb.test')->value('role'));
    }

    public function test_manajer_can_search_and_record_but_sees_no_full_list(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $dicari = Mahasiswa::create(['nama' => 'Budi', 'nim' => '2026001', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);
        Mahasiswa::create(['nama' => 'Ani', 'nim' => '2026002', 'jurusan' => 'TI', 'kelas_id' => $kelas->id]);

        $response = $this->actingAs($this->manajer())->get('/poin?q=2026001');

        $response->assertSee('Budi');
        $response->assertDontSee('2026002');
        $this->assertSame([$dicari->id], $response->viewData('mahasiswa')->pluck('id')->all());
    }
}
