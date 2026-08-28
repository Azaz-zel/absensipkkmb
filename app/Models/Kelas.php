<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama_kelas', 'kategori', 'kapasitas', 'status'])]
class Kelas extends Model
{
    use HasFactory;

    protected $table = 'kelas';

    /** @return HasMany<Mahasiswa, $this> */
    public function mahasiswa(): HasMany
    {
        return $this->hasMany(Mahasiswa::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function pj(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'kelas_user');
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
}
