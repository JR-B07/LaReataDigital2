<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accesos', function (Blueprint $table) {

            $table->id();

            $table->foreignId('id_boleto')
                ->constrained('boletos')
                ->cascadeOnDelete();

            $table->foreignId('id_usuario')
                ->constrained('usuarios')
                ->cascadeOnDelete();

            $table->timestamp('fecha_escaneo')->useCurrent();


        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos');
    }
};