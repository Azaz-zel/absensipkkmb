<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original unique index on `nim` counts soft-deleted rows too, so a
     * deleted student's NIM stays permanently blocked from reuse (e.g. re-importing
     * the same Excel file after "Hapus Semua"). Uniqueness among *active* students
     * is still enforced at the application layer (scoped to non-trashed rows).
     */
    public function up(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropUnique(['nim']);
            $table->index('nim');
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropIndex(['nim']);
            $table->unique('nim');
        });
    }
};
