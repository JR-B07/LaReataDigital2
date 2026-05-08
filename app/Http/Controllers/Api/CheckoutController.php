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
            'payment_status' => ['nullable', 'string', 'max:50'],
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
            $statusMap = [
                'approved' => 'pagado',
                'pending' => 'pendiente',
                'in_process' => 'procesando',
                'authorized' => 'autorizado',
                'rejected' => 'rechazado',
                'cancelled' => 'cancelado',
            ];

            $estadoPago = $statusMap[strtolower($data['payment_status'] ?? '')] ?? (
                $data['payment_method'] === 'card' ? 'pagado' : 'pendiente'
            );

            $order = Order::query()->create([
                // Las compras publicas no deben crear cuentas operativas reutilizables.
                'id_usuario' => null,
                'total' => $total,
                'metodo_pago' => match ($data['payment_method']) {
                    'card' => 'tarjeta',
                    'transfer' => 'transferencia',
                    default => 'efectivo',
                },
                'canal_venta' => 'online',
                'estado_pago' => $estadoPago,
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
            'payment_method' => ['required', 'in:card,oxxo,transfer'],
            'success_url' => ['nullable', 'url'],
            'failure_url' => ['nullable', 'url'],
            'pending_url' => ['nullable', 'url'],
        ]);

        $mercadoPagoConfig = config('services.mercadopago');
        $mode = $mercadoPagoConfig['mode'] ?? 'production';
        $token = $mode === 'sandbox'
            ? (string) ($mercadoPagoConfig['sandbox_access_token'] ?? $mercadoPagoConfig['access_token'])
            : (string) $mercadoPagoConfig['access_token'];

        if ($token === '') {
            return response()->json([
                'message' => 'Mercado Pago no configurado. Falta el token de acceso para el modo actual.',
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

        // No limitar el tipo de tarjeta aquí: Mercado Pago controla qué tarjetas acepta.
        // Solo controlemos cuotas y monto máximo para el checkout.

        $paymentMethods = null;
        if ($data['payment_method'] === 'card') {
            $paymentMethods = [
                'installments' => 6,
                'default_installments' => 1,
            ];
        } elseif ($data['payment_method'] === 'oxxo') {
            $paymentMethods = [
                'excluded_payment_types' => [
                    ['id' => 'atm'],
                    ['id' => 'credit_card'],
                    ['id' => 'debit_card'],
                    ['id' => 'bank_transfer'],
                ],
            ];
        } elseif ($data['payment_method'] === 'transfer') {
            $paymentMethods = [
                'excluded_payment_types' => [
                    ['id' => 'ticket'],
                    ['id' => 'atm'],
                    ['id' => 'credit_card'],
                    ['id' => 'debit_card'],
                ],
            ];
        }

        // Usar siempre la URL absoluta definida en APP_URL para Mercado Pago.
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

        $successUrl = trim($data['success_url'] ?? "{$baseUrl}/compra?event={$event->id}");
        $failureUrl = trim($data['failure_url'] ?? "{$baseUrl}/compra?event={$event->id}");
        $pendingUrl = trim($data['pending_url'] ?? "{$baseUrl}/compra?event={$event->id}");

        $backUrls = [
            'success' => $successUrl,
            'failure' => $failureUrl,
            'pending' => $pendingUrl,
        ];

        if ($data['payment_method'] === 'card' && $backUrls['success'] === '') {
            return response()->json([
                'message' => 'Para pagos con tarjeta es necesario definir success_url en el payload.',
            ], 422);
        }

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
                'phone' => [
                    'area_code' => '52',
                    'number' => preg_replace('/\D/', '', $data['buyer_phone'] ?? ''),
                ],
            ],
            'back_urls' => $backUrls,
            'external_reference' => "EV{$event->id}-ZN{$zone->id}-Q{$data['quantity']}",
            'statement_descriptor' => 'LAREATA DIGITAL',
            'notification_url' => "{$baseUrl}/api/webhook/mercadopago",
        ];

        if ($paymentMethods !== null) {
            $payload['payment_methods'] = $paymentMethods;
        }

        if ($data['payment_method'] === 'card' && config('app.env') !== 'local') {
            $payload['auto_return'] = 'approved';
        }

        $isSandbox = $mode === 'sandbox';
        if (config('app.env') === 'local' && ! $isSandbox) {
            Log::warning('Mercado Pago en entorno local con token de producción. Las tarjetas de prueba pueden fallar.', [
                'token_prefix' => substr($token, 0, 6),
            ]);
        }

        Log::debug('Mercado Pago preference payload', [
            'mode' => $mode,
            'base_url' => $baseUrl,
            'payload' => $payload,
        ]);

        $response = Http::withToken($token)
            ->acceptJson()
            ->post('https://api.mercadopago.com/checkout/preferences', $payload);

        Log::debug('Mercado Pago preference response', [
            'status' => $response->status(),
            'body' => $response->json(),
            'request' => $payload,
        ]);

        if (! $response->successful()) {
            return response()->json([
                'message' => 'No se pudo crear la preferencia de pago en Mercado Pago.',
                'details' => $response->json(),
                'request' => $payload,
            ], 422);
        }

        $redirectUrl = $response->json('init_point') ?: $response->json('sandbox_init_point');
        if ($isSandbox && $response->json('sandbox_init_point')) {
            $redirectUrl = $response->json('sandbox_init_point');
        }

        if (! $redirectUrl) {
            return response()->json([
                'message' => 'Mercado Pago no devolvió URL de redirección.',
                'details' => $response->json(),
            ], 422);
        }

        return response()->json([
            'redirect_url' => $redirectUrl,
            'preference_id' => $response->json('id'),
            'back_urls' => $backUrls,
            'base_url' => $baseUrl,
            'mode' => $isSandbox ? 'sandbox' : 'production',
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
