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

class AutoAlpaTest extends TestCase
{
    use RefreshDatabase;

    private SesiAbsensi $sesi;

    private Kelas $kelasA;

    private Kelas $kelasB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kelasA = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $this->kelasB = Kelas::create(['nama_kelas' => 'B', 'kategori' => 'malam', 'status' => 'aktif']);
        $this->sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now()]);

        foreach ([$this->kelasA, $this->kelasB] as $kelas) {
            SesiKelas::create([
                'sesi_absensi_id' => $this->sesi->id, 'kelas_id' => $kelas->id, 'status' => 'dibuka',
            ]);
        }
    }

    private function maba(Kelas $kelas, string $nim): Mahasiswa
    {
        return Mahasiswa::create([
            'nama' => "Maba {$nim}", 'nim' => $nim, 'jurusan' => 'TI', 'kelas_id' => $kelas->id,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
    }

    private function statusAbsensi(Mahasiswa $mahasiswa): ?string
    {
        return Absensi::where('mahasiswa_id', $mahasiswa->id)
            ->where('sesi_absensi_id', $this->sesi->id)
            ->value('status');
    }

    public function test_closing_a_class_marks_unrecorded_students_as_alpa(): void
    {
        $hadir = $this->maba($this->kelasA, '001');
        $belumDiabsen = $this->maba($this->kelasA, '002');
        $admin = $this->admin();

        Absensi::create([
            'mahasiswa_id' => $hadir->id, 'kelas_id' => $this->kelasA->id,
            'sesi_absensi_id' => $this->sesi->id, 'status' => 'hadir',
        ]);

        $this->actingAs($admin)
            ->post("/sesi/{$this->sesi->id}/{$this->kelasA->id}/tutup")
            ->assertRedirect();

        $this->assertSame('hadir', $this->statusAbsensi($hadir));
        $this->assertSame('alpa', $this->statusAbsensi($belumDiabsen));
    }

    public function test_auto_alpa_does_not_overwrite_izin_or_sakit(): void
    {
        $izin = $this->maba($this->kelasA, '001');
        $sakit = $this->maba($this->kelasA, '002');
        $admin = $this->admin();

        foreach ([[$izin, 'izin'], [$sakit, 'sakit']] as [$mahasiswa, $status]) {
            Absensi::create([
                'mahasiswa_id' => $mahasiswa->id, 'kelas_id' => $this->kelasA->id,
                'sesi_absensi_id' => $this->sesi->id, 'status' => $status,
            ]);
        }

        $this->actingAs($admin)->post("/sesi/{$this->sesi->id}/{$this->kelasA->id}/tutup");

        $this->assertSame('izin', $this->statusAbsensi($izin));
        $this->assertSame('sakit', $this->statusAbsensi($sakit));
    }

    public function test_closing_twice_does_not_duplicate_alpa_rows(): void
    {
        $mahasiswa = $this->maba($this->kelasA, '001');
        $admin = $this->admin();

        $this->actingAs($admin)->post("/sesi/{$this->sesi->id}/{$this->kelasA->id}/tutup");
        $this->actingAs($admin)->post("/sesi/{$this->sesi->id}/{$this->kelasA->id}/buka");
        $this->actingAs($admin)->post("/sesi/{$this->sesi->id}/{$this->kelasA->id}/tutup");

        $this->assertSame(1, Absensi::where('mahasiswa_id', $mahasiswa->id)
            ->where('sesi_absensi_id', $this->sesi->id)->count());
    }

    public function test_auto_alpa_only_touches_the_classes_being_closed(): void
    {
        $diTutup = $this->maba($this->kelasA, '001');
        $masihDibuka = $this->maba($this->kelasB, '002');
        $admin = $this->admin();

        $this->actingAs($admin)->post("/sesi/{$this->sesi->id}/{$this->kelasA->id}/tutup");

        $this->assertSame('alpa', $this->statusAbsensi($diTutup));
        $this->assertNull($this->statusAbsensi($masihDibuka));
    }

    public function test_auto_alpa_records_who_closed_the_session(): void
    {
        $mahasiswa = $this->maba($this->kelasA, '001');
        $admin = $this->admin();

        $this->actingAs($admin)->post("/sesi/{$this->sesi->id}/{$this->kelasA->id}/tutup");

        $this->assertSame($admin->id, Absensi::where('mahasiswa_id', $mahasiswa->id)->value('recorded_by'));
    }

    public function test_opening_a_class_never_creates_alpa_rows(): void
    {
        $this->maba($this->kelasA, '001');
        $admin = $this->admin();

        $this->actingAs($admin)->post("/sesi/{$this->sesi->id}/{$this->kelasA->id}/buka");

        $this->assertSame(0, Absensi::count());
    }

    public function test_bulk_close_with_selected_scope_only_affects_checked_classes(): void
    {
        $diPilih = $this->maba($this->kelasA, '001');
        $tidakDipilih = $this->maba($this->kelasB, '002');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post("/sesi/{$this->sesi->id}/bulk", [
                'action' => 'tutup',
                'scope' => 'terpilih',
                'kelas_ids' => [$this->kelasA->id],
            ])
            ->assertRedirect(route('sesi.show', $this->sesi));

        $this->assertSame('ditutup', SesiKelas::where('kelas_id', $this->kelasA->id)->value('status'));
        $this->assertSame('dibuka', SesiKelas::where('kelas_id', $this->kelasB->id)->value('status'));

        $this->assertSame('alpa', $this->statusAbsensi($diPilih));
        $this->assertNull($this->statusAbsensi($tidakDipilih));
    }

    public function test_bulk_open_with_selected_scope_only_affects_checked_classes(): void
    {
        SesiKelas::where('sesi_absensi_id', $this->sesi->id)->update(['status' => 'belum_dibuka']);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/sesi/{$this->sesi->id}/bulk", [
            'action' => 'buka',
            'scope' => 'terpilih',
            'kelas_ids' => [$this->kelasB->id],
        ]);

        $this->assertSame('belum_dibuka', SesiKelas::where('kelas_id', $this->kelasA->id)->value('status'));
        $this->assertSame('dibuka', SesiKelas::where('kelas_id', $this->kelasB->id)->value('status'));
    }

    public function test_selected_scope_requires_at_least_one_kelas(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post("/sesi/{$this->sesi->id}/bulk", ['action' => 'tutup', 'scope' => 'terpilih'])
            ->assertSessionHasErrors('kelas_ids');

        $this->assertSame('dibuka', SesiKelas::where('kelas_id', $this->kelasA->id)->value('status'));
    }

    public function test_auto_alpa_feeds_the_points_system(): void
    {
        $mahasiswa = $this->maba($this->kelasA, '001');
        $admin = $this->admin();

        $this->actingAs($admin)->post("/sesi/{$this->sesi->id}/{$this->kelasA->id}/tutup");

        $segar = Mahasiswa::withCount([
            'absensi as izin_count' => fn ($q) => $q->where('status', 'izin'),
            'absensi as sakit_count' => fn ($q) => $q->where('status', 'sakit'),
            'absensi as alpa_count' => fn ($q) => $q->where('status', 'alpa'),
        ])->findOrFail($mahasiswa->id);

        $this->assertSame(5, $segar->poin());
        $this->assertSame('ringan', $segar->sanksi()['level']);
    }
}
