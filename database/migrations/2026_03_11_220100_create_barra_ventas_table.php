<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barra_ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_evento')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('id_usuario')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->enum('metodo_pago', ['efectivo', 'tarjeta', 'transferencia']);
            $table->decimal('total', 10, 2);
            $table->string('notas')->nullable();
            $table->timestamps();
        });

        Schema::create('barra_venta_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_venta')->constrained('barra_ventas')->cascadeOnDelete();
            $table->foreignId('id_producto')->constrained('barra_productos')->restrictOnDelete();
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barra_venta_detalle');
        Schema::dropIfExists('barra_ventas');
    }
};
