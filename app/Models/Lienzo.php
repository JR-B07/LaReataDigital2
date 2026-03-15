<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lienzo extends Model
{
    use HasFactory;

    protected $table = 'lienzos';

    protected $fillable = [
        'nombre',
        'ciudad',
        'capacidad_total',
    ];
}
