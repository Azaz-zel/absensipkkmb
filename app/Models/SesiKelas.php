<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sesi_absensi_id', 'kelas_id', 'status', 'opened_at', 'closed_at', 'opened_by', 'closed_by'])]
class SesiKelas extends Model
{
    use HasFactory;

    protected $table = 'sesi_kelas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isDibuka(): bool
    {
        return $this->status === 'dibuka';
    }

    /** @return BelongsTo<SesiAbsensi, $this> */
    public function sesiAbsensi(): BelongsTo
    {
        return $this->belongsTo(SesiAbsensi::class);
    }

    /** @return BelongsTo<Kelas, $this> */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pembuka(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** @return BelongsTo<User, $this> */
    public function penutup(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
