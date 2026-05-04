<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookLogController extends Controller
{
    /**
     * Listar webhooks de Mercado Pago
     */
    public function index(Request $request): JsonResponse
    {
        $query = WebhookLog::query();

        // Filtrar por tipo
        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }

        // Filtrar por estado
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filtrar por fecha
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->get('from_date'));
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->get('to_date'));
        }

        $webhooks = $query
            ->with('order')
            ->latest()
            ->paginate(15);

        return response()->json($webhooks);
    }

    /**
     * Ver detalles de un webhook
     */
    public function show(WebhookLog $webhookLog): JsonResponse
    {
        return response()->json($webhookLog->load('order'));
    }

    /**
     * Reintentar procesar un webhook fallido
     */
    public function retry(WebhookLog $webhookLog): JsonResponse
    {
        if ($webhookLog->status === 'success') {
            return response()->json([
                'message' => 'No se puede reintentar un webhook exitoso',
            ], 422);
        }

        if ($webhookLog->retry_count >= 5) {
            return response()->json([
                'message' => 'El webhook ha alcanzado el límite de reintentos',
            ], 422);
        }

        // Resetear estado para reprocessamiento
        $webhookLog->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        return response()->json([
            'message' => 'Webhook marcado para reintento',
            'webhook' => $webhookLog,
        ]);
    }

    /**
     * Obtener estadísticas de webhooks
     */
    public function stats(): JsonResponse
    {
        $total = WebhookLog::count();
        $success = WebhookLog::success()->count();
        $failed = WebhookLog::failed()->count();
        $pending = WebhookLog::pending()->count();

        return response()->json([
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'pending' => $pending,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
        ]);
    }

    /**
     * Obtener resumen por tipo
     */
    public function summaryByType(): JsonResponse
    {
        $summary = WebhookLog::query()
            ->selectRaw('type, status, COUNT(*) as count')
            ->groupBy('type', 'status')
            ->orderBy('type')
            ->get()
            ->groupBy('type')
            ->map(function ($group) {
                return $group->keyBy('status')->mapWithKeys(function ($item) {
                    return [$item->status => $item->count];
                });
            });

        return response()->json($summary);
    }
}
