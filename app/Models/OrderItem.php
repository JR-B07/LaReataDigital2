<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'venta_detalle';
    public $timestamps = false;

    protected $appends = [
        'unit_price',
    ];

    protected $fillable = [
        'id_venta',
        'id_boleto',
        'precio',
    ];

    public function getUnitPriceAttribute(): float
    {
        return (float) ($this->attributes['precio'] ?? 0);
    }
}
