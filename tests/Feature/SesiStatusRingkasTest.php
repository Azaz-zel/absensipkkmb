<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\SesiAbsensi;
use App\Models\SesiKelas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SesiStatusRingkasTest extends TestCase
{
    use RefreshDatabase;

    private function seedSesiWithKelasStatuses(array $statuses): SesiAbsensi
    {
        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);

        foreach ($statuses as $status) {
            $kelas = Kelas::create(['nama_kelas' => uniqid('K'), 'kategori' => 'pagi', 'status' => 'aktif']);
            SesiKelas::create(['sesi_absensi_id' => $sesi->id, 'kelas_id' => $kelas->id, 'status' => $status]);
        }

        return $sesi;
    }

    private function withCounts(SesiAbsensi $sesi): SesiAbsensi
    {
        return SesiAbsensi::withCount([
            'sesiKelas as dibuka_count' => fn ($q) => $q->where('status', 'dibuka'),
            'sesiKelas as ditutup_count' => fn ($q) => $q->where('status', 'ditutup'),
            'sesiKelas as belum_dibuka_count' => fn ($q) => $q->where('status', 'belum_dibuka'),
        ])->findOrFail($sesi->id);
    }

    public function test_status_is_dibuka_when_any_kelas_is_open(): void
    {
        $sesi = $this->seedSesiWithKelasStatuses(['dibuka', 'ditutup', 'belum_dibuka']);

        $this->assertSame('dibuka', $this->withCounts($sesi)->statusRingkas());
    }

    public function test_status_is_ditutup_when_none_open_but_some_closed(): void
    {
        $sesi = $this->seedSesiWithKelasStatuses(['ditutup', 'belum_dibuka']);

        $this->assertSame('ditutup', $this->withCounts($sesi)->statusRingkas());
    }

    public function test_status_is_belum_dibuka_when_nothing_opened_or_closed_yet(): void
    {
        $sesi = $this->seedSesiWithKelasStatuses(['belum_dibuka', 'belum_dibuka']);

        $this->assertSame('belum_dibuka', $this->withCounts($sesi)->statusRingkas());
    }

    public function test_sesi_index_shows_a_single_status_badge_instead_of_raw_counts(): void
    {
        $this->seedSesiWithKelasStatuses(['dibuka', 'ditutup']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $response = $this->actingAs($admin)->get('/sesi');

        $response->assertDontSee('Belum Dibuka</th>', false);
        $response->assertSee('Status');
        $response->assertSee('Dibuka');
    }

    public function test_bulk_actions_use_a_checklist_instead_of_per_kategori_buttons(): void
    {
        $kelasPagi = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $kelasMalam = Kelas::create(['nama_kelas' => 'AA', 'kategori' => 'malam', 'status' => 'aktif']);
        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);
        SesiKelas::create(['sesi_absensi_id' => $sesi->id, 'kelas_id' => $kelasPagi->id, 'status' => 'belum_dibuka']);
        SesiKelas::create(['sesi_absensi_id' => $sesi->id, 'kelas_id' => $kelasMalam->id, 'status' => 'belum_dibuka']);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $response = $this->actingAs($admin)->get("/sesi/{$sesi->id}");

        $response->assertSee('Pilih Kelas');
        $response->assertSee('name="kelas_ids[]"', false);
        $response->assertSee('Buka Absensi');
        $response->assertSee('Tutup Absensi');

        $response->assertDontSee('Buka Kelas Pagi');
        $response->assertDontSee('Buka Kelas Malam');
        $response->assertDontSee('Tutup Kelas Pagi');
        $response->assertDontSee('Tutup Kelas Malam');
    }

    public function test_checklist_offers_quick_select_for_pagi_and_malam(): void
    {
        $kelasPagi = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $kelasMalam = Kelas::create(['nama_kelas' => 'AA', 'kategori' => 'malam', 'status' => 'aktif']);
        $kelasPagi2 = Kelas::create(['nama_kelas' => 'B', 'kategori' => 'pagi', 'status' => 'aktif']);
        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);

        foreach ([$kelasPagi, $kelasMalam, $kelasPagi2] as $kelas) {
            SesiKelas::create(['sesi_absensi_id' => $sesi->id, 'kelas_id' => $kelas->id, 'status' => 'belum_dibuka']);
        }

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $response = $this->actingAs($admin)->get("/sesi/{$sesi->id}");

        $response->assertSee('Pilih cepat:');
        $response->assertSee('Kelas Pagi (2)');
        $response->assertSee('Kelas Malam (1)');

        // Tiap checkbox membawa kategorinya supaya tombol pilih cepat tahu mana yang dicentang.
        $response->assertSee('data-kategori="pagi"', false);
        $response->assertSee('data-kategori="malam"', false);
    }

    public function test_opening_only_the_pagi_classes_leaves_malam_untouched(): void
    {
        $kelasPagi = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $kelasMalam = Kelas::create(['nama_kelas' => 'AA', 'kategori' => 'malam', 'status' => 'aktif']);
        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);

        foreach ([$kelasPagi, $kelasMalam] as $kelas) {
            SesiKelas::create(['sesi_absensi_id' => $sesi->id, 'kelas_id' => $kelas->id, 'status' => 'belum_dibuka']);
        }

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)->post("/sesi/{$sesi->id}/bulk", [
            'action' => 'buka',
            'scope' => 'terpilih',
            'kelas_ids' => [$kelasPagi->id],
        ])->assertRedirect();

        $this->assertSame('dibuka', SesiKelas::where('kelas_id', $kelasPagi->id)->value('status'));
        $this->assertSame('belum_dibuka', SesiKelas::where('kelas_id', $kelasMalam->id)->value('status'));
    }

    public function test_bulk_open_all_endpoint_still_works_with_semua_scope(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);
        SesiKelas::create(['sesi_absensi_id' => $sesi->id, 'kelas_id' => $kelas->id, 'status' => 'belum_dibuka']);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)
            ->post("/sesi/{$sesi->id}/bulk", ['action' => 'buka', 'scope' => 'semua'])
            ->assertRedirect(route('sesi.show', $sesi));

        $this->assertDatabaseHas('sesi_kelas', [
            'sesi_absensi_id' => $sesi->id,
            'kelas_id' => $kelas->id,
            'status' => 'dibuka',
        ]);
    }

    public function test_bulk_close_all_endpoint_still_works_with_semua_scope(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);
        SesiKelas::create(['sesi_absensi_id' => $sesi->id, 'kelas_id' => $kelas->id, 'status' => 'dibuka']);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $this->actingAs($admin)
            ->post("/sesi/{$sesi->id}/bulk", ['action' => 'tutup', 'scope' => 'semua'])
            ->assertRedirect(route('sesi.show', $sesi));

        $this->assertDatabaseHas('sesi_kelas', [
            'sesi_absensi_id' => $sesi->id,
            'kelas_id' => $kelas->id,
            'status' => 'ditutup',
        ]);
    }
}
