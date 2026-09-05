<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Kalau config sedang di-cache (bootstrap/cache/config.php), override dari
     * phpunit.xml tidak terbaca dan koneksi tetap menunjuk MySQL produksi.
     * RefreshDatabase kemudian menjalankan migrate:fresh dan menghapus data asli.
     * Ini pernah terjadi dua kali, jadi dihentikan di sini sebelum trait mana pun
     * sempat menyentuh database.
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $koneksi = config('database.default');
        $database = config("database.connections.{$koneksi}.database");

        if ($koneksi !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Test dihentikan: koneksi database mengarah ke [{$koneksi}: {$database}], "
                .'bukan sqlite in-memory. Penyebab paling umum adalah config yang masih di-cache. '
                .'Jalankan `php artisan config:clear` lalu ulangi test.'
            );
        }
    }
}
