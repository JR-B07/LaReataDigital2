<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuarios extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuarios';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'usuario',
        'telefono',
        'rol',
        'password',
        'activo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    protected $appends = [
        'name',
        'email',
        'phone',
        'role',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function getNameAttribute(): string
    {
        return (string) ($this->attributes['nombre'] ?? '');
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['nombre'] = $value;
    }

    public function getEmailAttribute(): string
    {
        return (string) ($this->attributes['usuario'] ?? '');
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['usuario'] = $value;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->attributes['telefono'] ?? null;
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['telefono'] = $value;
    }

    public function getRoleAttribute(): string
    {
        $rol = (string) ($this->attributes['rol'] ?? 'vendedor');

        return match ($rol) {
            'superadministrador', 'administrador' => 'admin',
            'checador' => 'validator',
            'promotor' => 'seller',
            default => 'seller',
        };
    }

    public function setRoleAttribute(?string $value): void
    {
        $this->attributes['rol'] = match ($value) {
            'admin' => 'administrador',
            'validator' => 'checador',
            'buyer', 'seller' => 'vendedor',
            default => $value ?: 'vendedor',
        };
    }

    public function isAdmin(): bool
    {
        return $this->getRoleAttribute() === 'admin';
    }

    public function isValidator(): bool
    {
        return $this->getRoleAttribute() === 'validator';
    }
}
