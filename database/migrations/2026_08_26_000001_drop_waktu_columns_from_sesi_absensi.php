<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesi_absensi', function (Blueprint $table) {
            $table->dropColumn(['waktu_mulai', 'waktu_selesai']);
        });
    }

    public function down(): void
    {
        Schema::table('sesi_absensi', function (Blueprint $table) {
            $table->time('waktu_mulai')->nullable();
            $table->time('waktu_selesai')->nullable();
        });
    }
};
