<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lienzos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('ciudad');
            $table->integer('capacidad_total');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lienzos');
    }
};