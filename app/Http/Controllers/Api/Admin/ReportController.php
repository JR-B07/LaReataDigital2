<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketScan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $eventId = $request->query('event_id');

        $orders = Order::query();
        $tickets = Ticket::query();
        $scans = TicketScan::query();

        if ($eventId) {
            $orders->where('event_id', $eventId);
            $tickets->where('event_id', $eventId);
            $scans->where('event_id', $eventId);
        }

        $soldTickets = (int) $tickets->count();
        $usedTickets = (int) $tickets->where('status', 'used')->count();

        return response()->json([
            'orders_count' => (int) $orders->count(),
            'tickets_sold' => $soldTickets,
            'revenue_total' => (float) $orders->sum('total'),
            'attendance_rate' => $soldTickets > 0 ? round(($usedTickets / $soldTickets) * 100, 2) : 0,
            'fraud_attempts' => (int) $scans->whereIn('result', ['invalid', 'used'])->count(),
        ]);
    }

    public function salesByZone(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
        ]);

        $rows = DB::table('order_items')
            ->join('event_zones', 'event_zones.id', '=', 'order_items.event_zone_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.event_id', $data['event_id'])
            ->groupBy('event_zones.id', 'event_zones.name')
            ->selectRaw('event_zones.name as zone, SUM(order_items.quantity) as tickets, SUM(order_items.subtotal) as amount')
            ->get();

        return response()->json($rows);
    }
}
