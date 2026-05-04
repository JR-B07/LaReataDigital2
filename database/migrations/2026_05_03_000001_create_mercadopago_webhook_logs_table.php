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
        Schema::create('mercadopago_webhook_logs', function (Blueprint $table) {
            $table->id();

            // Tipo de notificación
            $table->string('type')->index();

            // ID del recurso (payment, order, etc.)
            $table->string('resource_id')->nullable()->index();

            // ID de la orden asociada si existe
            $table->foreignId('order_id')->nullable()->constrained('ventas')->onDelete('set null');

            // Estado del procesamiento
            $table->enum('status', ['pending', 'processing', 'success', 'failed'])->default('pending')->index();

            // Payload completo del webhook
            $table->json('payload');

            // Respuesta generada
            $table->json('response')->nullable();

            // Mensaje de error si falló
            $table->text('error_message')->nullable();

            // Intentos de reprocessamiento
            $table->integer('retry_count')->default(0);

            // Fecha de procesamiento
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            // Índices para búsquedas
            $table->index(['type', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mercadopago_webhook_logs');
    }
};
