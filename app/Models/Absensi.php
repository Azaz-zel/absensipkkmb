<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['mahasiswa_id', 'kelas_id', 'sesi_absensi_id', 'status', 'recorded_by'])]
class Absensi extends Model
{
    use HasFactory;

    protected $table = 'absensi';

    /** @return BelongsTo<Mahasiswa, $this> */
    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    /** @return BelongsTo<Kelas, $this> */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /** @return BelongsTo<SesiAbsensi, $this> */
    public function sesiAbsensi(): BelongsTo
    {
        return $this->belongsTo(SesiAbsensi::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
