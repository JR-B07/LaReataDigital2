<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barra_cortes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_evento')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('id_usuario')->constrained('usuarios')->cascadeOnDelete();
            $table->decimal('monto_apertura', 10, 2);
            $table->decimal('monto_efectivo_esperado', 10, 2)->default(0);
            $table->decimal('monto_cierre', 10, 2)->nullable();
            $table->decimal('diferencia', 10, 2)->nullable();
            $table->enum('estado', ['abierto', 'cerrado'])->default('abierto');
            $table->timestamp('abierto_en');
            $table->timestamp('cerrado_en')->nullable();
            $table->string('notas_apertura')->nullable();
            $table->string('notas_cierre')->nullable();
            $table->timestamps();

            $table->index(['id_evento', 'id_usuario', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barra_cortes');
    }
};
