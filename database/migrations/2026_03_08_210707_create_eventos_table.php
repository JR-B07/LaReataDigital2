<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_lienzo')->constrained('lienzos')->cascadeOnDelete();
            $table->string('nombre');
            $table->date('fecha');
            $table->time('hora');

            $table->enum('estatus',['activo','cancelado','finalizado'])->default('activo');

            $table->enum('tipo_codigo',['barra','qr'])->default('barra');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};