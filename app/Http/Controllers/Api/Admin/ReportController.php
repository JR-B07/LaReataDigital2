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

        $salesSubquery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.event_id', $data['event_id'])
            ->groupBy('order_items.event_zone_id')
            ->selectRaw('order_items.event_zone_id, SUM(order_items.quantity) as tickets, SUM(order_items.subtotal) as amount');

        $attendanceSubquery = DB::table('tickets')
            ->where('tickets.event_id', $data['event_id'])
            ->groupBy('tickets.event_zone_id')
            ->selectRaw("tickets.event_zone_id, COUNT(*) as sold_tickets, SUM(CASE WHEN tickets.status = 'used' THEN 1 ELSE 0 END) as assisted");

        $rows = DB::table('event_zones')
            ->where('event_zones.event_id', $data['event_id'])
            ->leftJoinSub($salesSubquery, 'sales', function ($join) {
                $join->on('sales.event_zone_id', '=', 'event_zones.id');
            })
            ->leftJoinSub($attendanceSubquery, 'attendance', function ($join) {
                $join->on('attendance.event_zone_id', '=', 'event_zones.id');
            })
            ->selectRaw(
                "event_zones.name as zone,
                COALESCE(sales.tickets, 0) as tickets,
                COALESCE(sales.amount, 0) as amount,
                COALESCE(attendance.assisted, 0) as assisted,
                CASE
                    WHEN COALESCE(attendance.sold_tickets, 0) > 0
                        THEN ROUND((COALESCE(attendance.assisted, 0) / attendance.sold_tickets) * 100, 2)
                    ELSE 0
                END as attendance_rate"
            )
            ->orderBy('event_zones.id')
            ->get();

        return response()->json($rows);
    }
}
