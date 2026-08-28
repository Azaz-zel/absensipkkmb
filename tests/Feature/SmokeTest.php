<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\SesiAbsensi;
use App\Models\SesiKelas;
use App\Models\User;
use Database\Seeders\KelasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pj;

    private Kelas $kelasA;

    private Kelas $kelasB;

    private SesiAbsensi $sesi;

    protected function setUp(): void
    {
        parent::setUp();

        (new KelasSeeder())->run();

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $this->pj = User::factory()->create(['role' => 'pj', 'status' => 'aktif']);

        $this->kelasA = Kelas::where('nama_kelas', 'A')->first();
        $this->kelasB = Kelas::where('nama_kelas', 'B')->first();
        $this->pj->kelas()->attach([$this->kelasA->id]);

        foreach (range(1, 5) as $i) {
            Mahasiswa::create([
                'nama' => "Mahasiswa A{$i}", 'nim' => "A{$i}00001", 'jurusan' => 'Teknik Informatika', 'kelas_id' => $this->kelasA->id,
            ]);
        }
        foreach (range(1, 3) as $i) {
            Mahasiswa::create([
                'nama' => "Mahasiswa B{$i}", 'nim' => "B{$i}00001", 'jurusan' => 'Sistem Komputer', 'kelas_id' => $this->kelasB->id,
            ]);
        }

        $this->sesi = SesiAbsensi::create(['nama_sesi' => 'Sesi 1', 'tanggal' => now(), 'created_by' => $this->admin->id]);

        foreach (Kelas::all() as $kelas) {
            SesiKelas::create([
                'sesi_absensi_id' => $this->sesi->id,
                'kelas_id' => $kelas->id,
                'status' => in_array($kelas->id, [$this->kelasA->id, $this->kelasB->id]) ? 'dibuka' : 'belum_dibuka',
            ]);
        }

        foreach (Mahasiswa::where('kelas_id', $this->kelasA->id)->get() as $i => $m) {
            Absensi::create([
                'mahasiswa_id' => $m->id, 'kelas_id' => $this->kelasA->id, 'sesi_absensi_id' => $this->sesi->id,
                'status' => ['hadir', 'hadir', 'izin', 'sakit', 'alpa'][$i], 'recorded_by' => $this->admin->id,
            ]);
        }
    }

    public function test_every_admin_page_loads_without_error(): void
    {
        $routes = [
            '/',
            '/absensi',
            "/absensi/{$this->sesi->id}/{$this->kelasA->id}",
            '/mahasiswa',
            '/mahasiswa/create',
            '/mahasiswa/'.Mahasiswa::first()->id.'/edit',
            '/import',
            '/sesi',
            "/sesi/{$this->sesi->id}",
            '/rekap/mahasiswa',
            '/rekap/kelas',
            '/users',
            '/users/create',
            '/users/'.$this->pj->id.'/edit',
        ];

        foreach ($routes as $uri) {
            $response = $this->actingAs($this->admin)->get($uri);
            $response->assertOk("Admin GET {$uri} failed with status {$response->status()}");
        }
    }

    public function test_admin_can_download_every_export_and_template(): void
    {
        $this->actingAs($this->admin)->get('/import/template')->assertOk();
        $this->actingAs($this->admin)->get('/export/rekap')->assertOk();
        $this->actingAs($this->admin)->get('/export/kelas/'.$this->kelasA->id)->assertOk();
    }

    public function test_every_pj_page_loads_without_error_and_stays_scoped(): void
    {
        $routes = [
            '/',
            '/absensi',
            "/absensi/{$this->sesi->id}/{$this->kelasA->id}",
            '/rekap/mahasiswa',
            '/rekap/kelas',
        ];

        foreach ($routes as $uri) {
            $response = $this->actingAs($this->pj)->get($uri);
            $response->assertOk("PJ GET {$uri} failed with status {$response->status()}");
        }

        // PJ's rekap must never leak kelas B (not assigned to them).
        $this->actingAs($this->pj)->get('/rekap/mahasiswa')->assertDontSee('Mahasiswa B1');
        $this->actingAs($this->pj)->get('/rekap/kelas')->assertDontSeeText('Mahasiswa B1');
    }

    public function test_list_pages_do_not_have_n_plus_1_queries(): void
    {
        $checks = [
            'GET /mahasiswa' => fn () => $this->get('/mahasiswa'),
            'GET /rekap/mahasiswa' => fn () => $this->get('/rekap/mahasiswa'),
            'GET /rekap/kelas' => fn () => $this->get('/rekap/kelas'),
            'GET /absensi' => fn () => $this->get('/absensi?sesi='.$this->sesi->id),
            'GET /sesi/{id}' => fn () => $this->get("/sesi/{$this->sesi->id}"),
            'GET /users' => fn () => $this->get('/users'),
        ];

        $this->actingAs($this->admin);

        foreach ($checks as $label => $call) {
            DB::enableQueryLog();
            DB::flushQueryLog();

            $response = $call();
            $response->assertOk();

            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            // Fixed, small, list-size-independent upper bound: catches an obvious
            // N+1 (one query per row) without being a brittle exact-count assertion.
            $this->assertLessThan(20, $count, "{$label} ran {$count} queries — looks like an N+1.");
        }
    }
}
