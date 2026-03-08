<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_fila')->constrained('filas')->cascadeOnDelete();
            $table->integer('numero');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asientos');
    }
};