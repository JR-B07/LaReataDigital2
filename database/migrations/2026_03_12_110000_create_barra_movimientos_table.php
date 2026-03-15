<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barra_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_producto');
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->enum('tipo', ['entrada', 'salida_venta', 'merma', 'ajuste']);
            $table->integer('cantidad');
            $table->integer('stock_anterior');
            $table->integer('stock_nuevo');
            $table->string('motivo', 255)->nullable();
            $table->timestamps();

            $table->foreign('id_producto')
                ->references('id')
                ->on('barra_productos')
                ->onDelete('cascade');

            $table->foreign('id_usuario')
                ->references('id')
                ->on('usuarios')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barra_movimientos');
    }
};
