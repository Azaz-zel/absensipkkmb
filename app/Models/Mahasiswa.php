<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nama', 'nim', 'jurusan', 'kelas_id'])]
class Mahasiswa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mahasiswa';

    /** @return BelongsTo<Kelas, $this> */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /** @return HasMany<Absensi, $this> */
    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }

    /** @return HasMany<Pelanggaran, $this> */
    public function pelanggaran(): HasMany
    {
        return $this->hasMany(Pelanggaran::class);
    }

    /**
     * Poin dari absensi: alpa = 5 poin, sakit/izin = 3 poin, berlaku kelipatan.
     * Butuh hadir_count/izin_count/sakit_count/alpa_count dari withCount().
     */
    public function poin(): int
    {
        return ((int) ($this->alpa_count ?? 0) * 5)
            + (((int) ($this->sakit_count ?? 0) + (int) ($this->izin_count ?? 0)) * 3);
    }

    /** Poin untuk satu status absensi tunggal (dipakai buat rincian per sesi). */
    public static function poinUntukStatus(?string $status): int
    {
        return match ($status) {
            'alpa' => 5,
            'sakit', 'izin' => 3,
            default => 0,
        };
    }

    /**
     * Poin pelanggaran manual yang dicatat panitia.
     * Butuh alias pelanggaran_sum_poin dari withSum('pelanggaran', 'poin').
     */
    public function poinPelanggaran(): int
    {
        return (int) ($this->pelanggaran_sum_poin ?? 0);
    }

    /** Poin absensi + poin pelanggaran. */
    public function totalPoin(): int
    {
        return $this->poin() + $this->poinPelanggaran();
    }

    /** Sanksi yang didapat maba berdasarkan total poinnya. */
    public static function sanksiUntukPoin(int $poin): array
    {
        return match (true) {
            $poin >= 20 => ['label' => 'Cap merah - mengulang PKKMB', 'level' => 'merah'],
            $poin >= 10 => ['label' => 'Video: Problem and Solution', 'level' => 'berat'],
            $poin >= 6 => ['label' => 'Video: Edukasi Dijitalisasi', 'level' => 'sedang'],
            $poin >= 1 => ['label' => 'Video: PKKMB JOURNEY', 'level' => 'ringan'],
            default => ['label' => 'Tidak ada sanksi', 'level' => 'aman'],
        };
    }

    /** Sanksi berdasarkan total poin maba ini. */
    public function sanksi(): array
    {
        return static::sanksiUntukPoin($this->totalPoin());
    }
}
