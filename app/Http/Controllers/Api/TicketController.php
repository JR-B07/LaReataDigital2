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
            ->with(['event', 'order', 'item'])
            ->where(function ($query) use ($code) {
                $query->where('codigo_qr', $code)
                    ->orWhere('codigo_barras', $code);
            })
            ->firstOrFail();

        return response()->json($ticket);
    }

    public function downloadPdf(string $code)
    {
        $ticket = Ticket::query()
            ->with(['event', 'order', 'item'])
            ->where(function ($query) use ($code) {
                $query->where('codigo_qr', $code)
                    ->orWhere('codigo_barras', $code);
            })
            ->firstOrFail();

        $pdf = Pdf::loadView('tickets.pdf', ['ticket' => $ticket]);

        return $pdf->download("ticket-{$ticket->ticket_code}.pdf");
    }
}
