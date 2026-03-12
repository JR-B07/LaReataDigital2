<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EventZone extends Model
{
    use HasFactory;

    protected $table = 'zonas';

    protected $fillable = [
        'id_lienzo',
        'nombre',
        'precio',
    ];

    protected $appends = [
        'event_id',
        'name',
        'capacity',
        'price',
        'sold_count',
    ];

    public function lienzo()
    {
        return $this->belongsTo(Lienzo::class, 'id_lienzo');
    }

    public function getEventIdAttribute(): ?int
    {
        return Event::query()->where('id_lienzo', $this->id_lienzo)->value('id');
    }

    public function getNameAttribute(): string
    {
        return (string) ($this->attributes['nombre'] ?? '');
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['nombre'] = $value;
    }

    public function getPriceAttribute(): float
    {
        return (float) ($this->attributes['precio'] ?? 0);
    }

    public function setPriceAttribute($value): void
    {
        $this->attributes['precio'] = $value;
    }

    public function getCapacityAttribute(): int
    {
        return (int) DB::table('asientos')
            ->join('filas', 'filas.id', '=', 'asientos.id_fila')
            ->where('filas.id_zona', $this->id)
            ->count();
    }

    public function getSoldCountAttribute(): int
    {
        return (int) DB::table('boletos')
            ->join('asientos', 'asientos.id', '=', 'boletos.id_asiento')
            ->join('filas', 'filas.id', '=', 'asientos.id_fila')
            ->where('filas.id_zona', $this->id)
            ->whereIn('boletos.estado', ['vendido', 'usado'])
            ->count();
    }
}
