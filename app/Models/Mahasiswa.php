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

    /**
     * Poin pelanggaran: alpa = 5 poin, sakit/izin = 3 poin, berlaku kelipatan.
     * Butuh hadir_count/izin_count/sakit_count/alpa_count dari withCount().
     */
    public function poin(): int
    {
        return ($this->alpa_count * 5) + (($this->sakit_count + $this->izin_count) * 3);
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
}
