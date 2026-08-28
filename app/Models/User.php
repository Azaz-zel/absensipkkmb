<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'username', 'password', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'aktif';
    }

    /** @return BelongsToMany<Kelas, $this> */
    public function kelas(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'kelas_user');
    }

    /** @return HasMany<SesiAbsensi, $this> */
    public function sesiAbsensiDibuat(): HasMany
    {
        return $this->hasMany(SesiAbsensi::class, 'created_by');
    }

    /** @return HasMany<Absensi, $this> */
    public function absensiDicatat(): HasMany
    {
        return $this->hasMany(Absensi::class, 'recorded_by');
    }
}
