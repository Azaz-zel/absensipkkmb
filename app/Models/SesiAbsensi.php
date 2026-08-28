<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama_sesi', 'tanggal', 'created_by'])]
class SesiAbsensi extends Model
{
    use HasFactory;

    protected $table = 'sesi_absensi';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<SesiKelas, $this> */
    public function sesiKelas(): HasMany
    {
        return $this->hasMany(SesiKelas::class);
    }

    /** @return HasMany<Absensi, $this> */
    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }

    /**
     * Status ringkas satu sesi (dibuka jika ada kelas yang lagi dibuka, else ditutup
     * jika ada yang udah ditutup, else belum dibuka). Butuh dibuka_count/ditutup_count
     * dari withCount().
     */
    public function statusRingkas(): string
    {
        return match (true) {
            $this->dibuka_count > 0 => 'dibuka',
            $this->ditutup_count > 0 => 'ditutup',
            default => 'belum_dibuka',
        };
    }
}
