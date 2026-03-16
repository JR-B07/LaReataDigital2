<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barra_ventas', function (Blueprint $table) {
            $table->enum('estado', ['activa', 'cancelada', 'reembolsada'])
                ->default('activa')
                ->after('notas');
        });

        Schema::create('barra_reembolsos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_venta')
                ->constrained('barra_ventas')
                ->cascadeOnDelete();

            $table->foreignId('id_usuario')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete();

            $table->enum('tipo', ['total', 'parcial']);
            $table->decimal('monto', 10, 2);
            $table->string('motivo', 500);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barra_reembolsos');

        Schema::table('barra_ventas', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
