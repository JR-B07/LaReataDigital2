<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\TicketsPurchasedMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\TicketCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(private readonly TicketCodeService $ticketCodeService) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:eventos,id'],
            'event_zone_id' => ['required', 'exists:zonas,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_email' => ['required', 'string', 'max:255'],
            'buyer_phone' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['required', 'in:card,oxxo,transfer'],
            'discount_code' => ['nullable', 'string', 'max:50'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ]);

        $context = $this->resolveCheckoutContext($data);

        if (isset($context['error'])) {
            return response()->json([
                'message' => $context['error'],
            ], 422);
        }

        $event = $context['event'];
        $availableTickets = $context['available_tickets'];
        $subtotal = $context['subtotal'];
        $discountTotal = 0;
        $total = max(0, $subtotal - $discountTotal);

        $result = DB::transaction(function () use ($data, $event, $availableTickets, $subtotal, $total) {
            $order = Order::query()->create([
                'id_usuario' => null,
                'total' => $total,
                'metodo_pago' => match ($data['payment_method']) {
                    'card' => 'tarjeta',
                    'transfer' => 'transferencia',
                    default => 'efectivo',
                },
                'canal_venta' => 'online',
                'estado_pago' => 'pagado',
                'nombre_cliente' => $data['buyer_name'],
                'telefono_cliente' => $data['buyer_phone'] ?? null,
                'correo_cliente' => $data['buyer_email'],
                'referencia_pago' => $data['payment_reference'] ?? null,
            ]);

            $ticketRows = [];
            $ticketIds = [];

            foreach ($availableTickets as $ticket) {
                DB::table('boletos')->where('id', $ticket->id)->update([
                    'estado' => 'vendido',
                    'updated_at' => now(),
                ]);

                $ticketIds[] = $ticket->id;

                DB::table('venta_detalle')->insert([
                    'id_venta' => $order->id,
                    'id_boleto' => $ticket->id,
                    'precio' => $ticket->precio,
                ]);

                $ticketRows[] = [
                    'ticket_code' => $ticket->codigo_qr,
                ];
            }

            return [
                'order' => [
                    'id' => $order->id,
                    'total' => $order->total,
                    'subtotal' => $subtotal,
                ],
                'tickets' => $ticketRows,
                'ticket_ids' => $ticketIds,
            ];
        });

        $order = Order::query()->find($result['order']['id']);
        $tickets = Ticket::query()
            ->with(['event', 'order', 'item'])
            ->whereIn('id', $result['ticket_ids'])
            ->get()
            ->sortBy('id')
            ->values();

        $result['email_delivery'] = $this->deliverOrderTickets($order, $event, $tickets);
        unset($result['ticket_ids']);

        return response()->json($result, 201);
    }

    public function createMercadoPagoPreference(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:eventos,id'],
            'event_zone_id' => ['required', 'exists:zonas,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_email' => ['required', 'string', 'max:255'],
            'buyer_phone' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['required', 'in:card'],
            'success_url' => ['nullable', 'url'],
            'failure_url' => ['nullable', 'url'],
            'pending_url' => ['nullable', 'url'],
        ]);

        $token = (string) config('services.mercadopago.access_token');
        if ($token === '') {
            return response()->json([
                'message' => 'Mercado Pago no configurado. Falta MERCADOPAGO_ACCESS_TOKEN.',
            ], 422);
        }

        $context = $this->resolveCheckoutContext($data);

        if (isset($context['error'])) {
            return response()->json([
                'message' => $context['error'],
            ], 422);
        }

        $event = $context['event'];
        $zone = $context['zone'];
        $subtotal = $context['subtotal'];
        $total = max(0, $subtotal);

        $cardPaymentLimit = 20000;
        if ($data['payment_method'] === 'card' && $total > $cardPaymentLimit) {
            return response()->json([
                'message' => "El pago con tarjeta está limitado a MXN {$cardPaymentLimit}. Reduce la cantidad de boletos o selecciona otro método de pago.",
            ], 422);
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        if ($baseUrl === '') {
            return response()->json([
                'message' => 'APP_URL no está configurada. Actualiza tu archivo .env con la URL completa de tu aplicación.',
            ], 422);
        }

        $parsedBaseUrl = parse_url($baseUrl);
        if (! $parsedBaseUrl || empty($parsedBaseUrl['scheme']) || empty($parsedBaseUrl['host'])) {
            return response()->json([
                'message' => 'APP_URL no es una URL válida. Debe ser algo como https://marcamgr.devsistems.com.',
            ], 422);
        }

        if (config('app.env') !== 'local' && ($parsedBaseUrl['scheme'] ?? '') !== 'https') {
            return response()->json([
                'message' => 'APP_URL debe usar HTTPS en entornos de producción.',
            ], 422);
        }

        $successUrl = $data['success_url'] ?? "{$baseUrl}/checkout/success?event_id={$event->id}";
        $failureUrl = $data['failure_url'] ?? "{$baseUrl}/checkout/failure?event_id={$event->id}";
        $pendingUrl = $data['pending_url'] ?? "{$baseUrl}/checkout/pending?event_id={$event->id}";

        $backUrls = [
            'success' => $successUrl,
            'failure' => $failureUrl,
            'pending' => $pendingUrl,
        ];

        $payload = [
            'items' => [[
                'title' => "{$event->name} - {$zone->nombre}",
                'quantity' => (int) $data['quantity'],
                'currency_id' => 'MXN',
                'unit_price' => round($total / max(1, (int) $data['quantity']), 2),
            ]],
            'payer' => [
                'name' => $data['buyer_name'],
                'email' => $data['buyer_email'],
            ],
            'back_urls' => $backUrls,
            'external_reference' => "EV{$event->id}-ZN{$zone->id}-Q{$data['quantity']}",
            'statement_descriptor' => 'LAREATA DIGITAL',
            'notification_url' => "{$baseUrl}/api/webhook/mercadopago",
        ];

        if (!empty($backUrls['success'])) {
            $payload['auto_return'] = 'approved';
        }

        $payload['payment_methods'] = [
            'excluded_payment_methods' => [],
            'excluded_payment_types' => [],
            'installments' => 12,
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->post('https://api.mercadopago.com/checkout/preferences', $payload);

        if (! $response->successful()) {
            return response()->json([
                'message' => 'No se pudo crear la preferencia de pago en Mercado Pago.',
                'details' => $response->json(),
            ], 422);
        }

        $redirectUrl = $response->json('init_point') ?: $response->json('sandbox_init_point');
        if (str_starts_with($token, 'TEST-') && $response->json('sandbox_init_point')) {
            $redirectUrl = $response->json('sandbox_init_point');
        }

        return response()->json([
            'redirect_url' => $redirectUrl,
            'preference_id' => $response->json('id'),
            'back_urls' => $backUrls,
            'base_url' => $baseUrl,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        $orders = Order::query()
            ->where('id_usuario', $user->id)
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    private function resolveCheckoutContext(array $data): array
    {
        $event = Event::query()->where('estatus', 'activo')->findOrFail($data['event_id']);
        $zone = DB::table('zonas')
            ->where('id', $data['event_zone_id'])
            ->where('id_lienzo', $event->id_lienzo)
            ->first();

        if (! $zone) {
            return ['error' => 'La zona seleccionada no pertenece al evento.'];
        }

        $availableTickets = DB::table('boletos')
            ->join('asientos', 'asientos.id', '=', 'boletos.id_asiento')
            ->join('filas', 'filas.id', '=', 'asientos.id_fila')
            ->where('boletos.id_evento', $event->id)
            ->where('filas.id_zona', $zone->id)
            ->where('boletos.estado', 'disponible')
            ->select('boletos.id', 'boletos.precio', 'boletos.codigo_qr')
            ->limit($data['quantity'])
            ->get();

        if ($availableTickets->count() < $data['quantity']) {
            return ['error' => 'No hay suficientes boletos disponibles en esta zona.'];
        }

        return [
            'event' => $event,
            'zone' => $zone,
            'available_tickets' => $availableTickets,
            'subtotal' => (float) $availableTickets->sum('precio'),
        ];
    }

    private function deliverOrderTickets(?Order $order, Event $event, Collection $tickets): array
    {
        $mailer = (string) config('mail.default', 'log');
        $supportsExternalDelivery = ! in_array($mailer, ['log', 'array'], true);

        if (! $order || ! $order->buyer_email) {
            return [
                'attempted' => false,
                'sent' => false,
                'mode' => $mailer,
                'message' => 'La compra se registró, pero no hay un correo de destino para enviar los boletos.',
            ];
        }

        try {
            $attachments = $tickets->map(function (Ticket $ticket) {
                return [
                    'name' => "ticket-{$ticket->ticket_code}.pdf",
                    'data' => Pdf::loadView('tickets.pdf', ['ticket' => $ticket])->output(),
                ];
            })->all();

            Mail::to($order->buyer_email)->send(new TicketsPurchasedMail($order, $event, $tickets, $attachments));

            return [
                'attempted' => true,
                'sent' => $supportsExternalDelivery,
                'mode' => $mailer,
                'message' => $supportsExternalDelivery
                    ? 'Tus boletos fueron enviados a tu correo electrónico.'
                    : 'La compra se registró y el correo fue generado en modo local. Configura MAIL_MAILER con SMTP o un proveedor real para entregarlo a una bandeja externa.',
            ];
        } catch (Throwable $exception) {
            Log::error('No se pudieron enviar los boletos por correo.', [
                'order_id' => $order->id,
                'buyer_email' => $order->buyer_email,
                'mailer' => $mailer,
                'error' => $exception->getMessage(),
            ]);

            return [
                'attempted' => true,
                'sent' => false,
                'mode' => $mailer,
                'message' => 'La compra se registró, pero no se pudo enviar el correo. Puedes descargar los PDFs desde esta confirmación.',
            ];
        }
    }
}