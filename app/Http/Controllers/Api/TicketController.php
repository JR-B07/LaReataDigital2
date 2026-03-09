<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;

class TicketController extends Controller
{
    public function show(string $code): JsonResponse
    {
        $ticket = Ticket::query()
            ->with('event', 'zone', 'order', 'item')
            ->where('ticket_code', $code)
            ->firstOrFail();

        return response()->json($ticket);
    }

    public function downloadPdf(string $code)
    {
        $ticket = Ticket::query()
            ->with('event', 'zone', 'order', 'item')
            ->where('ticket_code', $code)
            ->firstOrFail();

        $pdf = Pdf::loadView('tickets.pdf', ['ticket' => $ticket]);

        return $pdf->download("ticket-{$ticket->ticket_code}.pdf");
    }
}
