<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketScan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValidatorController extends Controller
{
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ticket_code' => ['required', 'string'],
            'event_id' => ['nullable', 'exists:events,id'],
        ]);

        $ticket = Ticket::query()
            ->with('event', 'zone')
            ->where('ticket_code', $data['ticket_code'])
            ->first();

        if (! $ticket) {
            TicketScan::query()->create([
                'event_id' => $data['event_id'] ?? null,
                'validator_id' => $request->user()?->id,
                'scanned_code' => $data['ticket_code'],
                'result' => 'invalid',
                'message' => 'Código inexistente',
            ]);

            return response()->json([
                'result' => 'invalid',
                'color' => 'red',
                'message' => 'INVÁLIDO',
            ], 422);
        }

        if (! empty($data['event_id']) && (int) $data['event_id'] !== (int) $ticket->event_id) {
            return response()->json([
                'result' => 'invalid',
                'color' => 'red',
                'message' => 'El boleto no pertenece a este evento.',
            ], 422);
        }

        if ($ticket->status !== 'active') {
            TicketScan::query()->create([
                'event_id' => $ticket->event_id,
                'ticket_id' => $ticket->id,
                'validator_id' => $request->user()?->id,
                'scanned_code' => $ticket->ticket_code,
                'result' => 'used',
                'message' => 'Boleto ya utilizado',
            ]);

            return response()->json([
                'result' => 'used',
                'color' => 'orange',
                'message' => 'USADO',
            ], 409);
        }

        $ticket->update([
            'status' => 'used',
            'scanned_at' => now(),
        ]);

        TicketScan::query()->create([
            'event_id' => $ticket->event_id,
            'ticket_id' => $ticket->id,
            'validator_id' => $request->user()?->id,
            'scanned_code' => $ticket->ticket_code,
            'result' => 'valid',
            'message' => 'Boleto válido',
        ]);

        return response()->json([
            'result' => 'valid',
            'color' => 'green',
            'message' => 'VÁLIDO',
            'ticket' => [
                'code' => $ticket->ticket_code,
                'event' => $ticket->event->name,
                'zone' => $ticket->zone->name,
                'seat' => $ticket->seat,
            ],
        ]);
    }

    public function scans(Request $request): JsonResponse
    {
        $eventId = $request->query('event_id');

        $query = TicketScan::query()->latest()->limit(30);

        if ($eventId) {
            $query->where('event_id', $eventId);
        }

        if ($request->user()?->role === 'validator') {
            $query->where('validator_id', $request->user()->id);
        }

        return response()->json($query->get());
    }
}
