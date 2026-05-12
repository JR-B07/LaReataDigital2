<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Campo para almacenar el ID de la sesión de checkout de Conekta
            $table->string('conekta_session_id')->nullable()->unique()->after('mercadopago_response');

            // Campo para almacenar el ID del cargo de Conekta
            $table->string('conekta_charge_id')->nullable()->unique()->after('conekta_session_id');

            // Campo para almacenar el tipo de evento del webhook de Conekta
            $table->string('conekta_event_type')->nullable()->after('conekta_charge_id');

            // Campo para almacenar los detalles de respuesta de Conekta
            $table->json('conekta_response')->nullable()->after('conekta_event_type');

            // Índices para búsquedas rápidas
            $table->index('conekta_session_id');
            $table->index('conekta_charge_id');
            $table->index('conekta_event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex(['conekta_session_id']);
            $table->dropIndex(['conekta_charge_id']);
            $table->dropIndex(['conekta_event_type']);
            $table->dropColumn([
                'conekta_session_id',
                'conekta_charge_id',
                'conekta_event_type',
                'conekta_response',
            ]);
        });
    }
};
