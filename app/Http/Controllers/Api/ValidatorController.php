<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ValidatorController extends Controller
{
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ticket_code' => ['required', 'string'],
            'event_id' => ['nullable', 'exists:eventos,id'],
        ]);

        $ticket = Ticket::query()
            ->with('event')
            ->where(function ($query) use ($data) {
                $query->where('codigo_qr', $data['ticket_code'])
                    ->orWhere('codigo_barras', $data['ticket_code']);
            })
            ->first();

        if (! $ticket) {
            return response()->json([
                'result' => 'invalid',
                'color' => 'red',
                'message' => 'INVÁLIDO',
            ], 422);
        }

        if (! empty($data['event_id']) && (int) $data['event_id'] !== (int) $ticket->id_evento) {
            return response()->json([
                'result' => 'invalid',
                'color' => 'red',
                'message' => 'El boleto no pertenece a este evento.',
            ], 422);
        }

        if ($ticket->estado === 'usado') {

            return response()->json([
                'result' => 'used',
                'color' => 'orange',
                'message' => 'USADO',
            ], 409);
        }

        if ($ticket->estado !== 'vendido') {
            return response()->json([
                'result' => 'invalid',
                'color' => 'red',
                'message' => 'El boleto aun no esta vendido o no es valido.',
            ], 422);
        }

        $ticket->update([
            'estado' => 'usado',
            'escaneado' => true,
        ]);

        DB::table('accesos')->insert([
            'id_boleto' => $ticket->id,
            'id_usuario' => $request->user()?->id,
            'fecha_escaneo' => now(),
        ]);

        return response()->json([
            'result' => 'valid',
            'color' => 'green',
            'message' => 'VÁLIDO',
            'ticket' => [
                'code' => $ticket->ticket_code,
                'event' => $ticket->event->name,
                'zone' => 'Zona general',
                'seat' => $ticket->seat,
            ],
        ]);
    }

    public function scans(Request $request): JsonResponse
    {
        $rows = DB::table('accesos')
            ->join('boletos', 'boletos.id', '=', 'accesos.id_boleto')
            ->selectRaw('accesos.id, accesos.id_usuario as validator_id, boletos.id_evento as event_id, boletos.codigo_qr as scanned_code, accesos.fecha_escaneo')
            ->orderByDesc('accesos.id')
            ->limit(30)
            ->get();

        return response()->json($rows);
    }
}
