<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MercadoPagoWebhookRequest;
use App\Services\ConektaService;
use App\Services\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly MercadoPagoService $mercadoPagoService,
        private readonly ConektaService $conektaService
    ) {}

    /**
     * Recibir webhooks de Mercado Pago
     */
    public function mercadopago(MercadoPagoWebhookRequest $request): JsonResponse
    {
        try {
            // Validación ya hecha por FormRequest
            $data = $request->validated();

            Log::info('Webhook de Mercado Pago recibido', [
                'type' => $data['type'],
                'data.id' => $data['data']['id'],
            ]);

            // Procesar notificación de pago
            $success = $this->mercadoPagoService->processWebhookNotification($data);

            return response()->json([
                'success' => $success,
                'message' => 'Notificación procesada',
            ], $success ? 200 : 422);
        } catch (\Exception $exception) {
            Log::error('Error en webhook de Mercado Pago', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error procesando webhook',
            ], 500);
        }
    }

    /**
     * Recibir webhooks de Conekta
     */
    public function conekta(Request $request): JsonResponse
    {
        try {
            $data = $request->json()->all();

            Log::info('Webhook de Conekta recibido', [
                'type' => $data['type'] ?? 'unknown',
                'data.id' => $data['data']['object']['id'] ?? 'unknown',
            ]);

            // Procesar notificación
            $success = $this->conektaService->processWebhookNotification($data);

            return response()->json([
                'success' => $success,
                'message' => 'Notificación procesada',
            ], $success ? 200 : 422);
        } catch (\Exception $exception) {
            Log::error('Error en webhook de Conekta', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error procesando webhook',
            ], 500);
        }
    }

    /**
     * Endpoint de retorno para Mercado Pago (success)
     */
    public function mercadopagoSuccess(Request $request): JsonResponse
    {
        $paymentId = $request->query('payment_id');
        $preferenceId = $request->query('preference_id');

        Log::info('Mercado Pago success redirect', [
            'payment_id' => $paymentId,
            'preference_id' => $preferenceId,
        ]);

        return response()->json([
            'message' => 'Pago procesado exitosamente',
            'payment_id' => $paymentId,
            'preference_id' => $preferenceId,
        ]);
    }

    /**
     * Endpoint de retorno para Mercado Pago (failure)
     */
    public function mercadopagoFailure(Request $request): JsonResponse
    {
        $paymentId = $request->query('payment_id');
        $preferenceId = $request->query('preference_id');

        Log::warning('Mercado Pago payment failed', [
            'payment_id' => $paymentId,
            'preference_id' => $preferenceId,
        ]);

        return response()->json([
            'message' => 'El pago fue rechazado',
            'payment_id' => $paymentId,
            'preference_id' => $preferenceId,
        ], 422);
    }

    /**
     * Endpoint de retorno para Mercado Pago (pending)
     */
    public function mercadopagoPending(Request $request): JsonResponse
    {
        $paymentId = $request->query('payment_id');
        $preferenceId = $request->query('preference_id');

        Log::info('Mercado Pago payment pending', [
            'payment_id' => $paymentId,
            'preference_id' => $preferenceId,
        ]);

        return response()->json([
            'message' => 'El pago está pendiente de confirmación',
            'payment_id' => $paymentId,
            'preference_id' => $preferenceId,
        ]);
    }

    /**
     * Endpoint de retorno para Conekta (success)
     */
    public function conektaSuccess(Request $request): JsonResponse
    {
        $sessionId = $request->query('session_id');

        Log::info('Conekta success redirect', [
            'session_id' => $sessionId,
        ]);

        return response()->json([
            'message' => 'Pago procesado exitosamente',
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Endpoint de retorno para Conekta (failure)
     */
    public function conektaFailure(Request $request): JsonResponse
    {
        $sessionId = $request->query('session_id');

        Log::warning('Conekta payment failed', [
            'session_id' => $sessionId,
        ]);

        return response()->json([
            'message' => 'El pago fue rechazado o cancelado',
            'session_id' => $sessionId,
        ], 422);
    }
}
