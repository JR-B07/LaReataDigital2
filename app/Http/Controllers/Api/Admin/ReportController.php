<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $eventId = $request->query('event_id');

        $tickets = DB::table('boletos');

        if ($eventId) {
            $tickets->where('id_evento', $eventId);
        }

        $soldTickets = (int) (clone $tickets)->whereIn('estado', ['vendido', 'usado'])->count();
        $usedTickets = (int) (clone $tickets)->where('estado', 'usado')->count();

        $orderBase = DB::table('ventas');

        if ($eventId) {
            $orderBase
                ->join('venta_detalle', 'venta_detalle.id_venta', '=', 'ventas.id')
                ->join('boletos', 'boletos.id', '=', 'venta_detalle.id_boleto')
                ->where('boletos.id_evento', $eventId);
        }

        $ordersCount = (int) (clone $orderBase)->distinct('ventas.id')->count('ventas.id');
        $revenueTotal = (float) (clone $orderBase)->sum('ventas.total');

        return response()->json([
            'orders_count' => $ordersCount,
            'tickets_sold' => $soldTickets,
            'revenue_total' => $revenueTotal,
            'attendance_rate' => $soldTickets > 0 ? round(($usedTickets / $soldTickets) * 100, 2) : 0,
            'fraud_attempts' => 0,
        ]);
    }

    public function salesByZone(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:eventos,id'],
        ]);

        $event = DB::table('eventos')->where('id', $data['event_id'])->first();

        if (! $event) {
            return response()->json([]);
        }

        $soldSubquery = DB::table('boletos')
            ->join('asientos', 'asientos.id', '=', 'boletos.id_asiento')
            ->join('filas', 'filas.id', '=', 'asientos.id_fila')
            ->where('boletos.id_evento', $data['event_id'])
            ->whereIn('boletos.estado', ['vendido', 'usado'])
            ->groupBy('filas.id_zona')
            ->selectRaw('filas.id_zona as zone_id, COUNT(*) as tickets, SUM(CASE WHEN boletos.estado = "usado" THEN 1 ELSE 0 END) as assisted');

        $amountSubquery = DB::table('venta_detalle')
            ->join('boletos', 'boletos.id', '=', 'venta_detalle.id_boleto')
            ->join('asientos', 'asientos.id', '=', 'boletos.id_asiento')
            ->join('filas', 'filas.id', '=', 'asientos.id_fila')
            ->where('boletos.id_evento', $data['event_id'])
            ->groupBy('filas.id_zona')
            ->selectRaw('filas.id_zona as zone_id, SUM(venta_detalle.precio) as amount');

        $rows = DB::table('zonas')
            ->where('zonas.id_lienzo', $event->id_lienzo)
            ->leftJoinSub($soldSubquery, 'sold', function ($join) {
                $join->on('sold.zone_id', '=', 'zonas.id');
            })
            ->leftJoinSub($amountSubquery, 'sales', function ($join) {
                $join->on('sales.zone_id', '=', 'zonas.id');
            })
            ->selectRaw(
                "zonas.nombre as zone,
                COALESCE(sold.tickets, 0) as tickets,
                COALESCE(sales.amount, 0) as amount,
                COALESCE(sold.assisted, 0) as assisted,
                CASE
                    WHEN COALESCE(sold.tickets, 0) > 0
                        THEN ROUND((COALESCE(sold.assisted, 0) / sold.tickets) * 100, 2)
                    ELSE 0
                END as attendance_rate"
            )
            ->orderBy('zonas.id')
            ->get();

        return response()->json($rows);
    }
}
