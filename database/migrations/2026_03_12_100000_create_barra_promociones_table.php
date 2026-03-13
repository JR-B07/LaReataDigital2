<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barra_promociones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->enum('tipo', ['porcentaje', 'monto_fijo']);
            $table->decimal('valor', 10, 2);
            $table->unsignedBigInteger('id_producto')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('id_producto')
                ->references('id')
                ->on('barra_productos')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barra_promociones');
    }
};
