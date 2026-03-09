<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boletos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_evento')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('id_asiento')->constrained('asientos')->cascadeOnDelete();

            $table->decimal('precio',10,2);

            $table->string('codigo_barras')->unique();
            $table->string('codigo_qr')->unique();

            $table->enum('estado',[
                'disponible',
                'apartado',
                'vendido',
                'usado',
                'cancelado'
            ])->default('disponible');
            $table->boolean('escaneado')->default(false);

            $table->foreignId('id_promotor')->nullable()->constrained('promotores')->nullOnDelete();

            $table->timestamps();

            $table->unique(['id_evento','id_asiento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boletos');
    }
};
