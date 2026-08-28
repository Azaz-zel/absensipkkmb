<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesi_absensi_id')->constrained('sesi_absensi')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->enum('status', ['belum_dibuka', 'dibuka', 'ditutup'])->default('belum_dibuka');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sesi_absensi_id', 'kelas_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_kelas');
    }
};
