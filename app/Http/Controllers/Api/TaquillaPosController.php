<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Services\TicketCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaquillaPosController extends Controller
{
    public function __construct(private readonly TicketCodeService $ticketCodeService) {}

    /**
     * Eventos activos con disponibilidad resumida.
     */
    public function availableEvents(): JsonResponse
    {
        $events = DB::table('eventos')
            ->where('estatus', 'activo')
            ->orderByDesc('fecha_inicio')
            ->get();

        $eventsWithZones = $events->map(function ($event) {
            $zones = DB::table('zonas')
                ->join('lienzos', 'lienzos.id', '=', 'zonas.id_lienzo')
                ->where('lienzos.id', $event->id_lienzo)
                ->select('zonas.id', 'zonas.nombre', 'zonas.precio')
                ->get();

            foreach ($zones as $zone) {
                $zone->disponibles = (int) DB::table('boletos')
                    ->join('asientos', 'asientos.id', '=', 'boletos.id_asiento')
                    ->join('filas', 'filas.id', '=', 'asientos.id_fila')
                    ->where('boletos.id_evento', $event->id)
                    ->where('filas.id_zona', $zone->id)
                    ->where('boletos.estado', 'disponible')
                    ->count();
            }

            $event->zonas = $zones;

            return $event;
        });

        return response()->json($eventsWithZones);
    }

    /**
     * Disponibilidad detallada de una zona.
     */
    public function zoneAvailability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:eventos,id'],
            'zone_id' => ['required', 'exists:zonas,id'],
        ]);

        $available = (int) DB::table('boletos')
            ->join('asientos', 'asientos.id', '=', 'boletos.id_asiento')
            ->join('filas', 'filas.id', '=', 'asientos.id_fila')
            ->where('boletos.id_evento', (int) $data['event_id'])
            ->where('filas.id_zona', (int) $data['zone_id'])
            ->where('boletos.estado', 'disponible')
            ->count();

        $total = (int) DB::table('boletos')
            ->join('asientos', 'asientos.id', '=', 'boletos.id_asiento')
            ->join('filas', 'filas.id', '=', 'asientos.id_fila')
            ->where('boletos.id_evento', (int) $data['event_id'])
            ->where('filas.id_zona', (int) $data['zone_id'])
            ->count();

        $zone = DB::table('zonas')->where('id', (int) $data['zone_id'])->first();

        return response()->json([
            'zone_id' => (int) $data['zone_id'],
            'zone_name' => $zone->nombre ?? '',
            'price' => (float) ($zone->precio ?? 0),
            'available' => $available,
            'total' => $total,
        ]);
    }

    /**
     * Vender boletos en taquilla (presencial).
     */
    public function sellTickets(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:eventos,id'],
            'zone_id' => ['required', 'exists:zonas,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_phone' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['required', 'in:cash,card,transfer'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ]);

        $event = Event::query()->where('estatus', 'activo')->findOrFail($data['event_id']);

        $zone = DB::table('zonas')
            ->where('id', (int) $data['zone_id'])
            ->where('id_lienzo', $event->id_lienzo)
            ->first();

        if (! $zone) {
            return response()->json(['message' => 'La zona no pertenece al evento.'], 422);
        }

        $availableTickets = DB::table('boletos')
            ->join('asientos', 'asientos.id', '=', 'boletos.id_asiento')
            ->join('filas', 'filas.id', '=', 'asientos.id_fila')
            ->where('boletos.id_evento', $event->id)
            ->where('filas.id_zona', $zone->id)
            ->where('boletos.estado', 'disponible')
            ->select('boletos.id', 'boletos.precio', 'boletos.codigo_qr')
            ->limit((int) $data['quantity'])
            ->get();

        if ($availableTickets->count() < (int) $data['quantity']) {
            return response()->json([
                'message' => 'No hay suficientes boletos disponibles en esta zona.',
            ], 422);
        }

        $subtotal = (float) $availableTickets->sum('precio');

        $result = DB::transaction(function () use ($data, $event, $availableTickets, $subtotal, $request) {
            $order = Order::query()->create([
                'id_usuario' => $request->user()?->id,
                'total' => $subtotal,
                'metodo_pago' => match ($data['payment_method']) {
                    'card' => 'tarjeta',
                    'transfer' => 'transferencia',
                    default => 'efectivo',
                },
                'canal_venta' => 'taquilla',
                'estado_pago' => 'pagado',
                'nombre_cliente' => $data['buyer_name'],
                'telefono_cliente' => $data['buyer_phone'] ?? null,
                'correo_cliente' => null,
                'referencia_pago' => $data['payment_reference'] ?? null,
            ]);

            $ticketRows = [];

            foreach ($availableTickets as $ticket) {
                DB::table('boletos')->where('id', $ticket->id)->update([
                    'estado' => 'vendido',
                    'updated_at' => now(),
                ]);

                DB::table('venta_detalle')->insert([
                    'id_venta' => $order->id,
                    'id_boleto' => $ticket->id,
                    'precio' => $ticket->precio,
                ]);

                $ticketRows[] = [
                    'ticket_id' => $ticket->id,
                    'ticket_code' => $ticket->codigo_qr,
                    'precio' => $ticket->precio,
                ];
            }

            return [
                'order_id' => $order->id,
                'total' => $subtotal,
                'tickets' => $ticketRows,
            ];
        });

        return response()->json([
            'message' => 'Venta de taquilla registrada.',
            'sale' => $result,
        ], 201);
    }

    /**
     * Ventas recientes de taquilla del operador actual.
     */
    public function recentSales(Request $request): JsonResponse
    {
        $rows = DB::table('ventas')
            ->leftJoin('usuarios', 'usuarios.id', '=', 'ventas.id_usuario')
            ->where('ventas.canal_venta', 'taquilla')
            ->when(
                $request->user()?->role !== 'admin' && $request->user()?->role !== 'administrador' && $request->user()?->role !== 'superadministrador',
                fn($q) => $q->where('ventas.id_usuario', $request->user()?->id)
            )
            ->select(
                'ventas.id',
                'ventas.total',
                'ventas.metodo_pago',
                'ventas.estado_pago',
                'ventas.nombre_cliente',
                'ventas.created_at',
                'usuarios.nombre as vendedor'
            )
            ->orderByDesc('ventas.id')
            ->limit(30)
            ->get();

        // Contar boletos por venta
        foreach ($rows as $row) {
            $row->num_boletos = (int) DB::table('venta_detalle')
                ->where('id_venta', $row->id)
                ->count();
        }

        return response()->json($rows);
    }

    /**
     * Cancelar venta de taquilla (devolver boletos a disponible).
     */
    public function cancelSale(Request $request, int $sale): JsonResponse
    {
        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        $venta = DB::table('ventas')->where('id', $sale)->first();

        if (! $venta) {
            return response()->json(['message' => 'Venta no encontrada.'], 404);
        }

        if ($venta->canal_venta !== 'taquilla') {
            return response()->json(['message' => 'Solo se pueden cancelar ventas de taquilla.'], 422);
        }

        if ($venta->estado_pago === 'cancelado') {
            return response()->json(['message' => 'Esta venta ya fue cancelada.'], 422);
        }

        DB::transaction(function () use ($venta, $data) {
            DB::table('ventas')
                ->where('id', $venta->id)
                ->update([
                    'estado_pago' => 'cancelado',
                    'referencia_pago' => 'CANCELADO: ' . $data['motivo'],
                    'updated_at' => now(),
                ]);

            // Devolver boletos a disponible
            $detalles = DB::table('venta_detalle')
                ->where('id_venta', $venta->id)
                ->get();

            foreach ($detalles as $detalle) {
                DB::table('boletos')
                    ->where('id', $detalle->id_boleto)
                    ->update([
                        'estado' => 'disponible',
                        'updated_at' => now(),
                    ]);
            }
        });

        return response()->json(['message' => 'Venta cancelada y boletos liberados.']);
    }
}
