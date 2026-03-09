<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
       Schema::create('ventas', function (Blueprint $table) {

    $table->id();

    $table->foreignId('id_usuario')
        ->nullable()
        ->constrained('usuarios')
        ->nullOnDelete();

    $table->decimal('total',10,2);

    $table->enum('metodo_pago',[
        'efectivo',
        'transferencia',
        'tarjeta'
    ]);

    $table->enum('canal_venta',[
        'taquilla',
        'online',
        'promotor'
    ])->default('taquilla');

    $table->enum('estado_pago',[
        'pendiente',
        'pagado',
        'cancelado'
    ])->default('pendiente');

    $table->string('nombre_cliente')->nullable();
    $table->string('telefono_cliente')->nullable();
    $table->string('correo_cliente')->nullable();
    $table->string('referencia_pago')->nullable();

    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};