<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $table = 'eventos';

    protected $fillable = [
        'id_lienzo',
        'nombre',
        'fecha',
        'hora',
        'estatus',
        'tipo_codigo',
    ];

    protected $appends = [
        'name',
        'description',
        'city',
        'venue',
        'starts_at',
        'ends_at',
        'status',
        'barcode_format',
    ];

    public function lienzo()
    {
        return $this->belongsTo(Lienzo::class, 'id_lienzo');
    }

    public function zones()
    {
        return $this->hasMany(EventZone::class, 'id_lienzo', 'id_lienzo');
    }

    public function getNameAttribute(): string
    {
        return (string) ($this->attributes['nombre'] ?? '');
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['nombre'] = $value;
    }

    public function getDescriptionAttribute(): ?string
    {
        return null;
    }

    public function setDescriptionAttribute($value): void
    {
        // No persisted description column in the new schema.
    }

    public function getCityAttribute(): string
    {
        return (string) ($this->lienzo?->ciudad ?? '');
    }

    public function setCityAttribute($value): void
    {
        // Derived from lienzo.
    }

    public function getVenueAttribute(): string
    {
        return (string) ($this->lienzo?->nombre ?? '');
    }

    public function setVenueAttribute($value): void
    {
        // Derived from lienzo.
    }

    public function getStartsAtAttribute(): ?string
    {
        $fecha = $this->attributes['fecha'] ?? null;
        $hora = $this->attributes['hora'] ?? null;

        if (! $fecha || ! $hora) {
            return null;
        }

        return Carbon::parse("{$fecha} {$hora}")->toIso8601String();
    }

    public function setStartsAtAttribute(?string $value): void
    {
        if (! $value) {
            return;
        }

        $dt = Carbon::parse($value);
        $this->attributes['fecha'] = $dt->toDateString();
        $this->attributes['hora'] = $dt->format('H:i:s');
    }

    public function getEndsAtAttribute(): ?string
    {
        return null;
    }

    public function setEndsAtAttribute($value): void
    {
        // No ends_at in the new schema.
    }

    public function getStatusAttribute(): string
    {
        $estatus = (string) ($this->attributes['estatus'] ?? 'activo');

        return match ($estatus) {
            'activo' => 'published',
            'cancelado' => 'canceled',
            default => 'draft',
        };
    }

    public function setStatusAttribute(?string $value): void
    {
        $this->attributes['estatus'] = match ($value) {
            'published' => 'activo',
            'canceled' => 'cancelado',
            default => 'finalizado',
        };
    }

    public function getBarcodeFormatAttribute(): string
    {
        return (($this->attributes['tipo_codigo'] ?? 'barra') === 'qr') ? 'qr' : 'code128';
    }

    public function setBarcodeFormatAttribute(?string $value): void
    {
        $this->attributes['tipo_codigo'] = ($value === 'qr') ? 'qr' : 'barra';
    }
}
