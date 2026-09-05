<?php

use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\MahasiswaController;
use App\Http\Controllers\PoinController;
use App\Http\Controllers\RekapController;
use App\Http\Controllers\SesiAbsensiController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'account.active', 'session.idle'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Absensi & rekap: admin + PJ saja (manajer hanya urus poin)
    Route::middleware('role:admin,pj')->group(function () {
        // Absensi (shared, scoped per-kelas by class.access)
        Route::get('/absensi', [AbsensiController::class, 'index'])->name('absensi.index');
        Route::get('/absensi/{sesi}/{kelas}', [AbsensiController::class, 'show'])
            ->name('absensi.show')->middleware('class.access');
        Route::put('/absensi/{sesi}/{kelas}', [AbsensiController::class, 'update'])
            ->name('absensi.update')->middleware('class.access');

        // Rekap (shared, scoped for PJ inside the controller)
        Route::get('/rekap/mahasiswa', [RekapController::class, 'perMahasiswa'])->name('rekap.mahasiswa');
        Route::get('/rekap/kelas', [RekapController::class, 'perKelas'])->name('rekap.kelas');
        Route::get('/rekap/mahasiswa/{mahasiswa}', [RekapController::class, 'detailMahasiswa'])->name('rekap.mahasiswa.detail');
    });

    // Poin pelanggaran: admin (kelola penuh) + manajer (cari & catat)
    Route::middleware('role:admin,manajer')->group(function () {
        Route::get('/poin', [PoinController::class, 'index'])->name('poin.index');
        Route::post('/poin/{mahasiswa}/catat', [PoinController::class, 'store'])->name('poin.store');
    });

    // Admin-only
    Route::middleware('role:admin')->group(function () {
        Route::resource('kelas', KelasController::class)->except('show')->parameters(['kelas' => 'kelas']);

        Route::resource('mahasiswa', MahasiswaController::class)->except('show');
        Route::delete('/mahasiswa/massal/terpilih', [MahasiswaController::class, 'destroySelected'])->name('mahasiswa.destroySelected');
        Route::delete('/mahasiswa/massal/kelas/{kelas}', [MahasiswaController::class, 'destroyByKelas'])->name('mahasiswa.destroyByKelas');
        Route::delete('/mahasiswa/massal/semua', [MahasiswaController::class, 'destroyAll'])->name('mahasiswa.destroyAll');

        Route::put('/absensi-record/{absensi}', [AbsensiController::class, 'updateRecord'])->name('absensi.record.update');
        Route::delete('/absensi-record/{absensi}', [AbsensiController::class, 'destroyRecord'])->name('absensi.record.destroy');

        Route::get('/import', [ImportController::class, 'create'])->name('import.create');
        Route::get('/import/template', [ImportController::class, 'template'])->name('import.template');
        Route::post('/import/preview', [ImportController::class, 'preview'])->name('import.preview');
        Route::post('/import', [ImportController::class, 'store'])->name('import.store');

        Route::get('/sesi', [SesiAbsensiController::class, 'index'])->name('sesi.index');
        Route::post('/sesi', [SesiAbsensiController::class, 'store'])->name('sesi.store');
        Route::get('/sesi/{sesi}', [SesiAbsensiController::class, 'show'])->name('sesi.show');
        Route::delete('/sesi/{sesi}', [SesiAbsensiController::class, 'destroy'])->name('sesi.destroy');
        Route::post('/sesi/{sesi}/bulk', [SesiAbsensiController::class, 'bulk'])->name('sesi.bulk');
        Route::post('/sesi/{sesi}/{kelas}/buka', [SesiAbsensiController::class, 'openClass'])->name('sesi.openClass');
        Route::post('/sesi/{sesi}/{kelas}/tutup', [SesiAbsensiController::class, 'closeClass'])->name('sesi.closeClass');
        Route::post('/sesi/{sesi}/{kelas}/buka-kembali', [SesiAbsensiController::class, 'reopen'])->name('sesi.reopen');

        Route::resource('users', UserController::class)->except('show');
        Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggleStatus');

        Route::delete('/pelanggaran/{pelanggaran}', [PoinController::class, 'destroy'])->name('pelanggaran.destroy');

        Route::get('/export/rekap', [ExportController::class, 'rekap'])->name('export.rekap');
        Route::get('/export/poin', [ExportController::class, 'poin'])->name('export.poin');
        Route::get('/export/kelas/{kelas}', [ExportController::class, 'detailKelas'])->name('export.detailKelas');
    });
});
