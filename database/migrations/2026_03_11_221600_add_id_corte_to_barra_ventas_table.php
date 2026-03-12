<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barra_ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('barra_ventas', 'id_corte')) {
                $table->foreignId('id_corte')->nullable()->after('id_usuario')->constrained('barra_cortes')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('barra_ventas', function (Blueprint $table) {
            if (Schema::hasColumn('barra_ventas', 'id_corte')) {
                $table->dropForeign(['id_corte']);
                $table->dropColumn('id_corte');
            }
        });
    }
};
