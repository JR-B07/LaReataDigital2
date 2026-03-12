<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketScan extends Model
{
    use HasFactory;

    protected $table = 'accesos';
    public $timestamps = false;

    protected $fillable = [
        'id_boleto',
        'id_usuario',
        'fecha_escaneo',
    ];
}
