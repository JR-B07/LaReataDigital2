<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Ticket extends Model
{
    use HasFactory;

    protected $table = 'boletos';

    protected $fillable = [
        'id_evento',
        'id_asiento',
        'precio',
        'codigo_barras',
        'codigo_qr',
        'estado',
        'escaneado',
        'id_promotor',
    ];

    protected $appends = [
        'ticket_code',
        'event_id',
        'status',
        'seat',
        'zone',
    ];

    protected function casts(): array
    {
        return [
            'escaneado' => 'boolean',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'id_evento');
    }

    public function getTicketCodeAttribute(): string
    {
        return (string) ($this->attributes['codigo_qr'] ?? $this->attributes['codigo_barras'] ?? '');
    }

    public function setTicketCodeAttribute(?string $value): void
    {
        $this->attributes['codigo_qr'] = $value;
        $this->attributes['codigo_barras'] = $value;
    }

    public function getEventIdAttribute(): ?int
    {
        return $this->attributes['id_evento'] ?? null;
    }

    public function getStatusAttribute(): string
    {
        $estado = (string) ($this->attributes['estado'] ?? 'disponible');

        return match ($estado) {
            'usado' => 'used',
            'vendido' => 'active',
            default => 'invalid',
        };
    }

    public function setStatusAttribute(?string $value): void
    {
        $this->attributes['estado'] = match ($value) {
            'used' => 'usado',
            'active' => 'vendido',
            default => 'cancelado',
        };
    }

    public function getSeatAttribute(): ?string
    {
        $seat = DB::table('asientos')
            ->join('filas', 'filas.id', '=', 'asientos.id_fila')
            ->where('asientos.id', $this->attributes['id_asiento'] ?? null)
            ->select('filas.nombre as row_name', 'asientos.numero as seat_number')
            ->first();

        if (! $seat) {
            return null;
        }

        return trim(sprintf('%s %s', $seat->row_name, $seat->seat_number));
    }

    public function getZoneAttribute(): ?object
    {
        return DB::table('asientos')
            ->join('filas', 'filas.id', '=', 'asientos.id_fila')
            ->join('zonas', 'zonas.id', '=', 'filas.id_zona')
            ->where('asientos.id', $this->attributes['id_asiento'] ?? null)
            ->select('zonas.id', 'zonas.nombre as name', 'zonas.precio as price')
            ->first();
    }

    public function item()
    {
        return $this->hasOne(OrderItem::class, 'id_boleto');
    }

    public function order()
    {
        return $this->hasOneThrough(
            Order::class,
            OrderItem::class,
            'id_boleto',
            'id',
            'id',
            'id_venta',
        );
    }
}
