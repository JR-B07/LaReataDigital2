<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Mail\TicketsPurchasedMail;
use App\Models\Event;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTicketsAfterPayment implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentReceived $event): void
    {
        try {
            $order = $event->order;
            $paymentInfo = $event->paymentInfo;

            // Solo procesar si el pago fue aprobado
            if ($paymentInfo['status'] !== 'approved') {
                Log::info('Pago no aprobado, saltando envío de boletos', [
                    'order_id' => $order->id,
                    'status' => $paymentInfo['status'],
                ]);
                return;
            }

            // Obtener el evento asociado
            $eventModel = Event::find($order->items->first()?->event_id ?? null);
            if (!$eventModel) {
                Log::warning('No se encontró evento para orden', [
                    'order_id' => $order->id,
                ]);
                return;
            }

            // Obtener todos los boletos de la orden
            $tickets = Ticket::query()
                ->with(['event', 'order', 'item'])
                ->whereHas('item', function ($query) use ($order) {
                    $query->where('id_venta', $order->id);
                })
                ->get()
                ->sortBy('id')
                ->values();

            if ($tickets->isEmpty()) {
                Log::warning('No hay boletos para la orden', [
                    'order_id' => $order->id,
                ]);
                return;
            }

            // Generar PDFs de boletos
            $attachments = $tickets->map(function (Ticket $ticket) {
                return [
                    'name' => "ticket-{$ticket->ticket_code}.pdf",
                    'data' => Pdf::loadView('tickets.pdf', ['ticket' => $ticket])->output(),
                ];
            })->all();

            // Enviar email con boletos
            Mail::to($order->buyer_email)->send(
                new TicketsPurchasedMail($order, $eventModel, $tickets, $attachments)
            );

            Log::info('Boletos enviados después de pago aprobado', [
                'order_id' => $order->id,
                'tickets_count' => $tickets->count(),
                'email' => $order->buyer_email,
            ]);
        } catch (\Exception $exception) {
            Log::error('Error enviando boletos después de pago', [
                'order_id' => $event->order->id,
                'error' => $exception->getMessage(),
            ]);

            // Relanzar la excepción para que se reintenté
            throw $exception;
        }
    }
}
