<?php

namespace App\Services;

use App\Events\PaymentReceived;
use App\Models\Order;
use App\Models\WebhookLog;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoService
{
    protected string $accessToken;
    protected string $baseUrl;

    public function __construct()
    {
        $this->accessToken = (string) config('services.mercadopago.access_token');
        $this->baseUrl = rtrim((string) config('app.url'), '/');
    }

    /**
     * Crear una preferencia de pago para Mercado Pago
     */
    public function createPreference(array $data): array
    {
        if (! $this->accessToken) {
            return [
                'success' => false,
                'message' => 'Mercado Pago no está configurado. Falta MERCADOPAGO_ACCESS_TOKEN.',
            ];
        }

        try {
            $preference = [
                'items' => [[
                    'title' => $data['title'],
                    'quantity' => $data['quantity'],
                    'currency_id' => 'MXN',
                    'unit_price' => round($data['unit_price'], 2),
                ]],
                'payer' => [
                    'name' => $data['payer_name'],
                    'email' => $data['payer_email'],
                    'phone' => [
                        'area_code' => '52',
                        'number' => $this->sanitizePhone($data['payer_phone'] ?? ''),
                    ],
                ],
                'back_urls' => [
                    'success' => "{$this->baseUrl}/api/checkout/success",
                    'failure' => "{$this->baseUrl}/api/checkout/failure",
                    'pending' => "{$this->baseUrl}/api/checkout/pending",
                ],
                'external_reference' => $data['external_reference'] ?? '',
                'statement_descriptor' => 'LAREATA DIGITAL',
                'notification_url' => "{$this->baseUrl}/api/webhook/mercadopago",
                'auto_return' => 'approved',
            ];

            $response = Http::withToken($this->accessToken)
                ->acceptJson()
                ->post('https://api.mercadopago.com/checkout/preferences', $preference);

            if (! $response->successful()) {
                Log::error('Error al crear preferencia de Mercado Pago', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Error al crear preferencia de pago',
                    'details' => $response->json(),
                ];
            }

            return [
                'success' => true,
                'preference_id' => $response->json('id'),
                'init_point' => $response->json('init_point'),
                'sandbox_init_point' => $response->json('sandbox_init_point'),
            ];
        } catch (RequestException $exception) {
            Log::error('Error HTTP al crear preferencia de Mercado Pago', [
                'error' => $exception->getMessage(),
                'response' => $exception->response?->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Error de comunicación con Mercado Pago',
                'details' => $exception->getMessage(),
            ];
        } catch (\Exception $exception) {
            Log::error('Error inesperado en MercadoPago', [
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error inesperado',
                'details' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Obtener información de pago por ID de transacción
     */
    public function getPaymentInfo(string $paymentId): ?array
    {
        if (! $this->accessToken) {
            return null;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->acceptJson()
                ->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

            if (! $response->successful()) {
                Log::error('Error al obtener información de pago de Mercado Pago', [
                    'payment_id' => $paymentId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $payment = $response->json();

            return [
                'id' => $payment['id'] ?? null,
                'status' => $payment['status'] ?? null,
                'status_detail' => $payment['status_detail'] ?? null,
                'transaction_amount' => $payment['transaction_amount'] ?? null,
                'currency_id' => $payment['currency_id'] ?? null,
                'external_reference' => $payment['external_reference'] ?? null,
                'payer_email' => $payment['payer']['email'] ?? null,
                'payment_method_id' => $payment['payment_method_id'] ?? null,
            ];
        } catch (RequestException $exception) {
            Log::error('Error HTTP al obtener información de pago de Mercado Pago', [
                'payment_id' => $paymentId,
                'error' => $exception->getMessage(),
                'response' => $exception->response?->body(),
            ]);

            return null;
        } catch (\Exception $exception) {
            Log::error('Error inesperado al obtener información de pago de Mercado Pago', [
                'payment_id' => $paymentId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Procesar notificación de webhook de Mercado Pago
     */
    public function processWebhookNotification(array $data): bool
    {
        try {
            // Registrar webhook en BD
            $webhookLog = $this->logWebhook($data);

            // Validar que sea una notificación de pago
            if (($data['type'] ?? null) !== 'payment') {
                $webhookLog->markAsSuccess(['message' => 'Tipo de notificación no soportado, ignorado']);
                return true; // Ignorar si no es de tipo payment
            }

            $paymentId = $data['data']['id'] ?? null;
            if (! $paymentId) {
                $webhookLog->markAsFailed('No se proporcionó ID de pago');
                return false;
            }

            $webhookLog->markAsProcessing();

            // Obtener información del pago
            $paymentInfo = $this->getPaymentInfo((string) $paymentId);
            if (! $paymentInfo) {
                $webhookLog->markAsFailed('No se pudo obtener información del pago de Mercado Pago');
                return false;
            }

            // Buscar la orden asociada
            $order = Order::query()
                ->where('mercadopago_transaction_id', $paymentId)
                ->orWhere('referencia_pago', $paymentInfo['external_reference'] ?? '')
                ->first();

            if (! $order) {
                Log::warning('No se encontró orden para pago de Mercado Pago', [
                    'payment_id' => $paymentId,
                    'external_reference' => $paymentInfo['external_reference'] ?? '',
                ]);

                $webhookLog->markAsFailed('No se encontró orden asociada');
                return false;
            }

            // Actualizar estado de la orden según el estado del pago
            $this->updateOrderFromPayment($order, $paymentInfo);

            // Registrar webhook log ID en la orden
            $webhookLog->update(['order_id' => $order->id]);

            // Disparar evento de pago recibido
            PaymentReceived::dispatch($order, $paymentInfo, (string) $paymentId);

            // Marcar webhook como exitoso
            $webhookLog->markAsSuccess([
                'order_id' => $order->id,
                'payment_status' => $paymentInfo['status'],
            ]);

            return true;
        } catch (\Exception $exception) {
            Log::error('Error procesando webhook de Mercado Pago', [
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
     * Actualizar orden con información del pago
     */
    private function updateOrderFromPayment(Order $order, array $paymentInfo): void
    {
        $statusMap = [
            'approved' => 'pagado',
            'pending' => 'pendiente',
            'authorized' => 'autorizado',
            'in_process' => 'procesando',
            'rejected' => 'rechazado',
            'cancelled' => 'cancelado',
            'refunded' => 'reembolsado',
            'charged_back' => 'devolución',
        ];

        $order->update([
            'mercadopago_transaction_id' => $paymentInfo['id'],
            'mercadopago_payment_status' => $paymentInfo['status'],
            'estado_pago' => $statusMap[$paymentInfo['status']] ?? 'pendiente',
            'mercadopago_response' => $paymentInfo,
        ]);

        Log::info('Orden actualizada con información de Mercado Pago', [
            'order_id' => $order->id,
            'payment_id' => $paymentInfo['id'],
            'status' => $paymentInfo['status'],
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
     * Validar webhook de Mercado Pago (opcional pero recomendado)
     */
    public function validateWebhookSignature(array $headers, string $body): bool
    {
        // Mercado Pago no usa firma en webhooks estándar
        // Esta función es para implementación futura si lo requieren
        return true;
    }

    /**
     * Registrar webhook en la BD para auditoría
     */
    private function logWebhook(array $data): WebhookLog
    {
        return WebhookLog::create([
            'type' => $data['type'] ?? 'unknown',
            'resource_id' => $data['data']['id'] ?? null,
            'payload' => $data,
            'status' => 'pending',
        ]);
    }
}
