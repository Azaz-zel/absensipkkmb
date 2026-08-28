<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MahasiswaImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeUploadedXlsx(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['nama', 'nim', 'jurusan', 'kelas'], null, 'A1', true);

        foreach ($rows as $i => $row) {
            $sheet->fromArray($row, null, 'A'.($i + 2), true);
        }

        $path = storage_path('framework/testing/import-test.xlsx');
        @mkdir(dirname($path), recursive: true);
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_preview_flags_invalid_rows_and_confirm_only_imports_valid_ones(): void
    {
        $kelasA = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        Mahasiswa::create(['nama' => 'Existing', 'nim' => '11110001', 'jurusan' => 'TI', 'kelas_id' => $kelasA->id]);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $file = $this->makeUploadedXlsx([
            ['Valid One', '22220001', 'TI', 'A'],           // valid
            ['Dup Existing', '11110001', 'TI', 'A'],        // nim already in DB
            ['Bad Kelas', '22220002', 'TI', 'ZZ'],          // kelas doesn't exist
        ]);

        $preview = $this->actingAs($admin)->post('/import/preview', ['file' => $file]);

        $preview->assertOk();
        $preview->assertViewHas('summary', fn ($summary) => $summary['total'] === 3 && $summary['valid'] === 1 && $summary['error'] === 2);

        $token = $preview->viewData('token');

        $this->actingAs($admin)->post('/import', ['token' => $token])->assertRedirect('/mahasiswa');

        $this->assertDatabaseHas('mahasiswa', ['nim' => '22220001']);
        $this->assertDatabaseMissing('mahasiswa', ['nim' => '22220002']);
        $this->assertSame(2, Mahasiswa::count());
    }

    public function test_a_soft_deleted_students_nim_can_be_reimported_instead_of_staying_blocked_forever(): void
    {
        $kelasA = Kelas::create(['nama_kelas' => 'A', 'kategori' => 'pagi', 'status' => 'aktif']);
        $old = Mahasiswa::create(['nama' => 'Old Record', 'nim' => '33330001', 'jurusan' => 'TI', 'kelas_id' => $kelasA->id]);
        $old->delete(); // soft delete, e.g. via "Hapus Semua Data Kelas"

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);

        $file = $this->makeUploadedXlsx([
            ['Reimported', '33330001', 'TI', 'A'],
        ]);

        $preview = $this->actingAs($admin)->post('/import/preview', ['file' => $file]);
        $preview->assertViewHas('summary', fn ($summary) => $summary['valid'] === 1 && $summary['error'] === 0);

        $token = $preview->viewData('token');
        $this->actingAs($admin)->post('/import', ['token' => $token])->assertRedirect('/mahasiswa');

        $this->assertSame(1, Mahasiswa::where('nim', '33330001')->count());
        // Both the old trashed row and the freshly re-imported row coexist without a DB conflict.
        $this->assertSame(2, Mahasiswa::withTrashed()->where('nim', '33330001')->count());
    }
}
