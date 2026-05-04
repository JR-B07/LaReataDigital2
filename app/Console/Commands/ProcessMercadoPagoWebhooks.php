<?php

namespace App\Console\Commands;

use App\Models\WebhookLog;
use App\Services\MercadoPagoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessMercadoPagoWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mercadopago:process-webhooks {--retry-failed : Reintentar webhooks fallidos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesar webhooks pendientes de Mercado Pago';

    /**
     * Execute the console command.
     */
    public function handle(MercadoPagoService $mercadoPagoService): int
    {
        $retryFailed = $this->option('retry-failed');

        // Obtener webhooks pendientes
        $query = WebhookLog::pending();

        if ($retryFailed) {
            $this->info('Procesando webhooks fallidos con reintentos...');
            $query = WebhookLog::failed()
                ->where('retry_count', '<', 3);
        } else {
            $this->info('Procesando webhooks pendientes...');
        }

        $webhooks = $query->get();

        if ($webhooks->isEmpty()) {
            $this->info('No hay webhooks para procesar.');
            return 0;
        }

        $processed = 0;
        $failed = 0;

        foreach ($webhooks as $webhook) {
            try {
                $this->line("Procesando webhook #{$webhook->id} (tipo: {$webhook->type})...");

                $success = $mercadoPagoService->processWebhookNotification(
                    $webhook->payload
                );

                if ($success) {
                    $this->info("✓ Webhook #{$webhook->id} procesado exitosamente");
                    $processed++;
                } else {
                    $this->error("✗ Webhook #{$webhook->id} falló");
                    $failed++;
                }
            } catch (\Exception $exception) {
                $this->error("✗ Error procesando webhook #{$webhook->id}: {$exception->getMessage()}");
                $failed++;
            }
        }

        $this->info("\n" . str_repeat('=', 50));
        $this->line("Procesados: {$processed}");
        $this->line("Fallidos: {$failed}");
        $this->info(str_repeat('=', 50));

        Log::info('Comando ProcessMercadoPagoWebhooks ejecutado', [
            'processed' => $processed,
            'failed' => $failed,
            'retry_failed' => $retryFailed,
        ]);

        return $failed > 0 ? 1 : 0;
    }
}
