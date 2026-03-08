<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_zona')->constrained('zonas')->cascadeOnDelete();
            $table->string('nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filas');
    }
};