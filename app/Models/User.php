<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'negocio_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function negocio()
    {
        return $this->belongsTo(Negocio::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isGerente(): bool
    {
        return $this->role === 'gerente';
    }

    public function isCapturista(): bool
    {
        return $this->role === 'capturista';
    }

    // IDs de negocios que este usuario puede ver.
    // Admin ve todos. Capturista solo su negocio asignado. Gerente ve los no privados.
    public function negociosPermitidos()
    {
        if ($this->isAdmin()) {
            return null; // null = sin restricción
        }
        if ($this->isCapturista()) {
            return $this->negocio_id ? collect([$this->negocio_id]) : collect();
        }
        return Negocio::where('privado', false)->pluck('id');
    }
}
