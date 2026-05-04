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
            // Campo para almacenar el ID de la transacción de Mercado Pago
            $table->string('mercadopago_transaction_id')->nullable()->unique()->after('referencia_pago');

            // Campo para almacenar el ID de la preferencia de Mercado Pago
            $table->string('mercadopago_preference_id')->nullable()->after('mercadopago_transaction_id');

            // Campo para almacenar el estado del pago de Mercado Pago
            $table->string('mercadopago_payment_status')->nullable()->after('mercadopago_preference_id');

            // Campo para almacenar los detalles de respuesta de Mercado Pago
            $table->json('mercadopago_response')->nullable()->after('mercadopago_payment_status');

            // Índices para búsquedas rápidas
            $table->index('mercadopago_transaction_id');
            $table->index('mercadopago_preference_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex(['mercadopago_transaction_id']);
            $table->dropIndex(['mercadopago_preference_id']);
            $table->dropColumn([
                'mercadopago_transaction_id',
                'mercadopago_preference_id',
                'mercadopago_payment_status',
                'mercadopago_response',
            ]);
        });
    }
};
