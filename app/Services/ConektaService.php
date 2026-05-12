<?php

namespace App\Services;

use App\Events\PaymentReceived;
use App\Models\Order;
use App\Models\WebhookLog;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConektaService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $apiVersion;

    public function __construct()
    {
        $conektaConfig = config('services.conekta');
        $this->apiKey = (string) ($conektaConfig['api_key'] ?? '');
        $this->apiVersion = 'v2';
        $this->baseUrl = 'https://api.conekta.io';
    }

    /**
     * Crear una sesión de checkout para Conekta
     */
public function createCheckoutSession(array $data): array
{
    if (! $this->apiKey) {
        Log::error('Conekta API key missing');

        return [
            'success' => false,
            'message' => 'Conekta no está configurado. Falta CONEKTA_API_KEY.',
        ];
    }

    try {
$payload = [
    'line_items' => [[
        'name' => $data['title'],
        'description' => $data['description'] ?? '',
        'unit_price' => (int) round($data['unit_price'] * 100),
        'quantity' => (int) $data['quantity'],
    ]],
    'currency' => 'MXN',
    'customer_info' => [
        'name' => $data['payer_name'],
        'email' => $data['payer_email'],
        'phone' => $this->sanitizePhone($data['payer_phone'] ?? ''),
    ],
    'checkout' => [
        'type' => 'Integration',
        'allowed_payment_methods' => ['card'],
        'success_url' => $data['success_url'],
        'failure_url' => $data['failure_url'],
    ],
    'metadata' => [
        'external_reference' => $data['external_reference'] ?? '',
    ],
];
       

       $response = Http::withBasicAuth($this->apiKey, '')
    ->withHeaders([
        'Accept' => 'application/vnd.conekta-v2.3.0+json',
        'Content-Type' => 'application/json',
    ])
    ->post("{$this->baseUrl}/orders", $payload);
       
        if (! $response->successful()) {
            Log::error('=== CONEKTA REQUEST FAILED ===', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'response_body' => $response->body(),
                'response_json' => $response->json(),
                'sent_payload' => $payload,
                'api_key_prefix' => substr($this->apiKey, 0, 8),
            ]);

            return [
                'success' => false,
                'message' => 'Error al crear sesión de pago',
                'details' => $response->json(),
            ];
        }

$responseData = $response->json();

$orderId = $responseData['id'] ?? null;
$checkoutId = $responseData['channel']['checkout_request_id'] ?? null;
$checkoutUrl = $checkoutId ? "https://pay.conekta.com/checkout/{$checkoutId}" : null;



return [
    'success' => true,
    'order_id' => $orderId,
    'checkout_id' => $checkoutId,
    'checkout_url' => $checkoutUrl,
    'expires_at' => $responseData['checkout']['expires_at'] ?? null,
];
    } catch (\Illuminate\Http\Client\RequestException $exception) {
        Log::error('=== CONEKTA HTTP EXCEPTION ===', [
            'message' => $exception->getMessage(),
            'response' => $exception->response?->body(),
        ]);

        return [
            'success' => false,
            'message' => 'Error de comunicación con Conekta',
            'details' => $exception->getMessage(),
        ];

    } catch (\Exception $exception) {
        Log::error('=== CONEKTA UNKNOWN ERROR ===', [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        return [
            'success' => false,
            'message' => 'Error inesperado',
            'details' => $exception->getMessage(),
        ];
    }
}

    /**
     * Obtener información de cargo por ID
     */
    public function getChargeInfo(string $chargeId): ?array
    {
        if (! $this->apiKey) {
            return null;
        }

        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->accept('application/json')
                ->get("{$this->baseUrl}/{$this->apiVersion}/charges/{$chargeId}");

            if (! $response->successful()) {
                Log::error('Error al obtener información de cargo de Conekta', [
                    'charge_id' => $chargeId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $charge = $response->json();

            return [
                'id' => $charge['id'] ?? null,
                'status' => $charge['status'] ?? null,
                'amount' => $charge['amount'] ?? null,
                'currency' => $charge['currency'] ?? null,
                'payment_method' => $charge['payment_method']['type'] ?? null,
                'customer_email' => $charge['customer_info']['email'] ?? null,
                'metadata' => $charge['metadata'] ?? [],
            ];
        } catch (RequestException $exception) {
            Log::error('Error HTTP al obtener información de cargo de Conekta', [
                'charge_id' => $chargeId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        } catch (\Exception $exception) {
            Log::error('Error inesperado al obtener información de cargo de Conekta', [
                'charge_id' => $chargeId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Procesar notificación de webhook de Conekta
     */
    public function processWebhookNotification(array $data): bool
    {
        try {
            // Registrar webhook en BD
            $webhookLog = $this->logWebhook($data);

            $eventType = $data['type'] ?? null;

            // Solo procesar eventos de carga (charge) configurados en webhook
            if (! in_array($eventType, ['charge.paid', 'charge.pending_confirmation', 'charge.expired', 'charge.declined'], true)) {
                $webhookLog->markAsSuccess(['message' => 'Tipo de evento no procesable, ignorado']);
                return true;
            }

            $chargeId = $data['data']['object']['id'] ?? null;
            if (! $chargeId) {
                $webhookLog->markAsFailed('No se proporcionó ID de cargo');
                return false;
            }

            $webhookLog->markAsProcessing();

            // Obtener información del cargo
            $chargeInfo = $this->getChargeInfo($chargeId);
            if (! $chargeInfo) {
                $webhookLog->markAsFailed('No se pudo obtener información del cargo de Conekta');
                return false;
            }

            // Buscar la orden asociada
            $externalReference = $data['data']['object']['metadata']['external_reference'] ?? '';
            $order = Order::query()
                ->where('conekta_charge_id', $chargeId)
                ->orWhere('referencia_pago', $externalReference)
                ->first();

            if (! $order) {

                $webhookLog->markAsFailed('No se encontró orden asociada');
                return false;
            }

            $this->updateOrderFromWebhookEvent($order, $eventType, $chargeInfo, $data);

            $webhookLog->update(['order_id' => $order->id]);

            PaymentReceived::dispatch($order, $chargeInfo, $chargeId);

            $webhookLog->markAsSuccess([
                'order_id' => $order->id,
                'event_type' => $eventType,
            ]);

            return true;
        } catch (\Exception $exception) {
            Log::error('Error procesando webhook de Conekta', [
                'error' => $exception->getMessage(),
                'data' => $data,
            ]);

            if (isset($webhookLog)) {
                $webhookLog->markAsFailed($exception->getMessage());
            }

            return false;
        }
    }

    /**
     * Actualizar orden con información del evento webhook
     */
    private function updateOrderFromWebhookEvent(Order $order, string $eventType, array $chargeInfo, array $webhookData): void
    {
        $statusMap = [
            'charge.paid' => 'pagado',
            'charge.pending_confirmation' => 'pendiente',
            'charge.expired' => 'expirado',
            'charge.declined' => 'rechazado',
        ];

        $order->update([
            'conekta_charge_id' => $chargeInfo['id'],
            'conekta_event_type' => $eventType,
            'estado_pago' => $statusMap[$eventType] ?? 'pendiente',
            'conekta_response' => $webhookData,
        ]);

    }

    /**
     * Sanitizar número telefónico
     */
    private function sanitizePhone(string $phone): string
    {
        // Remover todos los caracteres que no sean dígitos
        $digits = preg_replace('/\D/', '', $phone);

        // Si empieza con 52 (código de México), removerlo
        if (str_starts_with($digits, '52')) {
            $digits = substr($digits, 2);
        }

        // Retornar solo dígitos sin formatear
        return $digits;
    }

    /**
     * Registrar webhook en la base de datos
     */
    private function logWebhook(array $data): WebhookLog
    {
        return WebhookLog::query()->create([
            'provider' => 'conekta',
            'event_type' => $data['type'] ?? 'unknown',
            'payload' => $data,
            'status' => 'pending',
        ]);
    }

    /**
     * Validar webhook de Conekta
     */
    public function validateWebhookSignature(array $headers, string $body): bool
    {
        // Conekta puede usar X-Conekta-Webhook-Signature
        // Implementar validación si se configura el webhook signing
        return true;
    }
}
