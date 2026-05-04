<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'ventas';

    protected $appends = [
        'buyer_name',
        'buyer_email',
        'buyer_phone',
    ];

    protected $fillable = [
        'id_usuario',
        'total',
        'metodo_pago',
        'canal_venta',
        'estado_pago',
        'nombre_cliente',
        'telefono_cliente',
        'correo_cliente',
        'referencia_pago',
        'mercadopago_transaction_id',
        'mercadopago_preference_id',
        'mercadopago_payment_status',
        'mercadopago_response',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'id_venta');
    }

    public function getBuyerNameAttribute(): ?string
    {
        return $this->attributes['nombre_cliente'] ?? null;
    }

    public function getBuyerEmailAttribute(): ?string
    {
        return $this->attributes['correo_cliente'] ?? null;
    }

    public function getBuyerPhoneAttribute(): ?string
    {
        return $this->attributes['telefono_cliente'] ?? null;
    }
}
