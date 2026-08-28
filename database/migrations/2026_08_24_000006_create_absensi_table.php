<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('sesi_absensi_id')->constrained('sesi_absensi')->cascadeOnDelete();
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpa']);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['mahasiswa_id', 'sesi_absensi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi');
    }
};
