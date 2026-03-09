<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Models\Event;
use App\Models\EventZone;
use App\Models\Order;
use App\Models\User;
use App\Services\TicketCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(private readonly TicketCodeService $ticketCodeService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'event_zone_id' => ['required', 'exists:event_zones,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_email' => ['required', 'email', 'max:255'],
            'buyer_phone' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['required', 'in:card,oxxo,transfer'],
            'discount_code' => ['nullable', 'string', 'max:50'],
        ]);

        $event = Event::query()->where('status', 'published')->findOrFail($data['event_id']);
        $zone = EventZone::query()->where('event_id', $event->id)->findOrFail($data['event_zone_id']);

        $available = $zone->capacity - $zone->sold_count;
        if ($data['quantity'] > $available) {
            return response()->json([
                'message' => 'No hay suficientes boletos disponibles en esta zona.',
            ], 422);
        }

        $subtotal = $zone->price * $data['quantity'];
        $discountTotal = 0;
        $discount = null;

        if (! empty($data['discount_code'])) {
            $discount = DiscountCode::query()
                ->where('event_id', $event->id)
                ->where('code', Str::upper($data['discount_code']))
                ->where('is_active', true)
                ->first();

            if ($discount) {
                $isExpired = $discount->expires_at && $discount->expires_at->isPast();
                $maxReached = $discount->max_uses !== null && $discount->used_count >= $discount->max_uses;

                if (! $isExpired && ! $maxReached) {
                    $discountTotal = $discount->type === 'percent'
                        ? round($subtotal * ($discount->value / 100), 2)
                        : min($subtotal, $discount->value);
                }
            }
        }

        $total = max(0, $subtotal - $discountTotal);

        $order = DB::transaction(function () use ($data, $event, $zone, $subtotal, $discountTotal, $total, $discount) {
            // Guest checkout: keep order buyer data without requiring individual user profiles.
            $buyer = User::query()->firstOrCreate(
                ['email' => 'invitado@lareata.local'],
                [
                    'name' => 'Comprador Invitado',
                    'phone' => null,
                    'password' => Str::password(16),
                    'role' => 'buyer',
                ]
            );

            $order = Order::query()->create([
                'event_id' => $event->id,
                'user_id' => $buyer->id,
                'buyer_name' => $data['buyer_name'],
                'buyer_email' => $data['buyer_email'],
                'buyer_phone' => $data['buyer_phone'] ?? null,
                'payment_method' => $data['payment_method'],
                'payment_status' => 'paid',
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'total' => $total,
                'discount_code' => $discount?->code,
            ]);

            $item = $order->items()->create([
                'event_zone_id' => $zone->id,
                'quantity' => $data['quantity'],
                'unit_price' => $zone->price,
                'subtotal' => $subtotal,
            ]);

            for ($i = 0; $i < $data['quantity']; $i++) {
                $ticketCode = $this->ticketCodeService->generateUniqueCode();
                $payload = $this->ticketCodeService->makePayload([
                    'ticket_code' => $ticketCode,
                    'event_id' => $event->id,
                    'event_zone_id' => $zone->id,
                    'purchased_at' => now()->toISOString(),
                ]);

                $order->tickets()->create([
                    'event_id' => $event->id,
                    'order_item_id' => $item->id,
                    'event_zone_id' => $zone->id,
                    'user_id' => $buyer->id,
                    'ticket_code' => $ticketCode,
                    'qr_payload' => $payload,
                    'status' => 'active',
                ]);
            }

            $zone->increment('sold_count', $data['quantity']);

            if ($discount) {
                $discount->increment('used_count');
            }

            return $order->load('event', 'items.zone', 'tickets');
        });

        return response()->json($order, 201);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        $orders = Order::query()
            ->with('event', 'items.zone', 'tickets')
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }
}
