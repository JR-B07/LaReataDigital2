# Ejemplos de Código - Integración Conekta

## Frontend (Vue 3 + JavaScript)

### 1. Componente de Pago con Conekta

```vue
<template>
  <div class="conekta-checkout">
    <form @submit.prevent="handleCheckout">
      <div class="form-group">
        <label>Nombre</label>
        <input v-model="form.buyer_name" type="text" required>
      </div>
      
      <div class="form-group">
        <label>Email</label>
        <input v-model="form.buyer_email" type="email" required>
      </div>
      
      <div class="form-group">
        <label>Teléfono</label>
        <input v-model="form.buyer_phone" type="tel" required>
      </div>
      
      <div class="form-group">
        <label>Método de Pago</label>
        <select v-model="form.payment_method">
          <option value="card">Tarjeta de Crédito/Débito</option>
          <option value="oxxo">OXXO</option>
          <option value="bank_transfer">Transferencia Bancaria</option>
        </select>
      </div>
      
      <button type="submit" :disabled="loading">
        {{ loading ? 'Procesando...' : 'Ir a Pagar' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const form = ref({
  buyer_name: '',
  buyer_email: '',
  buyer_phone: '',
  payment_method: 'card',
});

const loading = ref(false);

const handleCheckout = async () => {
  loading.value = true;
  
  try {
    const response = await fetch('/api/checkout/conekta', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        event_id: route.params.eventId,
        event_zone_id: route.params.zoneId,
        quantity: quantity.value,
        ...form.value,
      }),
    });
    
    const data = await response.json();
    
    if (!response.ok) {
      alert('Error: ' + data.message);
      return;
    }
    
    // Redirigir al checkout de Conekta
    window.location.href = data.checkout_url;
  } catch (error) {
    console.error('Error:', error);
    alert('Error al procesar el pago');
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.conekta-checkout {
  max-width: 400px;
  margin: 0 auto;
  padding: 20px;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: bold;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 8px;
  border: 1px solid #ccc;
  border-radius: 4px;
}

button {
  width: 100%;
  padding: 10px;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 16px;
}

button:hover:not(:disabled) {
  background-color: #0056b3;
}

button:disabled {
  background-color: #ccc;
  cursor: not-allowed;
}
</style>
```

### 2. Página de Confirmación

```vue
<template>
  <div class="checkout-confirmation">
    <div v-if="status === 'success'" class="success-message">
      <h2>✅ Pago Exitoso</h2>
      <p>Tu pago ha sido procesado correctamente.</p>
      <p>Recibirás un email con tus boletos en breve.</p>
      <button @click="goHome">Volver al Inicio</button>
    </div>
    
    <div v-else-if="status === 'failure'" class="error-message">
      <h2>❌ Pago Fallido</h2>
      <p>El pago no pudo ser procesado. Por favor intenta de nuevo.</p>
      <button @click="goBack">Volver al Carrito</button>
    </div>
    
    <div v-else class="loading-message">
      <p>Procesando tu pago...</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const status = ref('loading');

onMounted(() => {
  if (route.query.success === 'true') {
    status.value = 'success';
  } else if (route.query.success === 'false') {
    status.value = 'failure';
  }
});

const goHome = () => {
  router.push('/');
};

const goBack = () => {
  router.back();
};
</script>

<style scoped>
.checkout-confirmation {
  text-align: center;
  padding: 40px 20px;
  min-height: 60vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

.success-message, .error-message, .loading-message {
  padding: 40px;
  border-radius: 8px;
}

.success-message {
  background-color: #d4edda;
  border: 1px solid #c3e6cb;
  color: #155724;
}

.error-message {
  background-color: #f8d7da;
  border: 1px solid #f5c6cb;
  color: #721c24;
}

.loading-message {
  font-size: 18px;
  color: #666;
}

button {
  margin-top: 20px;
  padding: 10px 20px;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

button:hover {
  background-color: #0056b3;
}
</style>
```

---

## Backend (Laravel PHP)

### 1. Controller - Obtener Estado de Orden

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderStatusController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $order = Order::findOrFail($request->order_id);
        
        return response()->json([
            'id' => $order->id,
            'total' => $order->total,
            'estado_pago' => $order->estado_pago,
            'conekta_charge_id' => $order->conekta_charge_id,
            'conekta_event_type' => $order->conekta_event_type,
            'items' => $order->items()->get(),
            'created_at' => $order->created_at->toIso8601String(),
        ]);
    }
}
```

### 2. Event Listener - Enviar Confirmación

```php
<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Mail\TicketsPurchasedMail;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendTicketsAfterConektaPayment
{
    public function handle(PaymentReceived $event): void
    {
        $order = $event->order;
        $chargeInfo = $event->paymentInfo;

        // Solo procesar si fue un pago de Conekta
        if (!$order->conekta_charge_id) {
            return;
        }

        // Solo enviar boletos si el pago fue aprobado
        if ($order->estado_pago !== 'pagado') {
            Log::info('Pago aún no confirmado', [
                'order_id' => $order->id,
                'status' => $order->estado_pago,
            ]);
            return;
        }

        try {
            // Obtener boletos de la orden
            $tickets = Ticket::whereIn('id', 
                $order->items()->pluck('id_boleto')->toArray()
            )->get();

            // Generar PDFs
            $attachments = $tickets->map(function (Ticket $ticket) {
                return [
                    'name' => "boleto-{$ticket->ticket_code}.pdf",
                    'data' => Pdf::loadView('tickets.pdf', ['ticket' => $ticket])->output(),
                    'mime' => 'application/pdf',
                ];
            })->toArray();

            // Enviar email
            Mail::to($order->buyer_email)->send(
                new TicketsPurchasedMail($order, $tickets, $attachments)
            );

            Log::info('Boletos enviados por email', [
                'order_id' => $order->id,
                'email' => $order->buyer_email,
                'ticket_count' => $tickets->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al enviar boletos', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

### 3. Service - Extensiones del ConektaService

```php
<?php

namespace App\Services;

use App\Models\Order;

class ConektaService
{
    // ... métodos existentes ...

    /**
     * Crear orden pendiente de Conekta
     */
    public function createPendingOrder(array $checkoutData, string $sessionId): Order
    {
        return Order::create([
            'id_usuario' => null,
            'total' => $checkoutData['total'],
            'metodo_pago' => 'conekta',
            'canal_venta' => 'online',
            'estado_pago' => 'pendiente',
            'nombre_cliente' => $checkoutData['buyer_name'],
            'telefono_cliente' => $checkoutData['buyer_phone'],
            'correo_cliente' => $checkoutData['buyer_email'],
            'conekta_session_id' => $sessionId,
            'referencia_pago' => $checkoutData['external_reference'],
        ]);
    }

    /**
     * Obtener estado legible del cargo
     */
    public function getStatusLabel(string $status): string
    {
        return match($status) {
            'paid' => 'Pagado',
            'pending' => 'Pendiente',
            'expired' => 'Expirado',
            'failed' => 'Rechazado',
            default => 'Desconocido',
        };
    }

    /**
     * Enviar webhook de prueba
     */
    public function sendTestWebhook(Order $order): bool
    {
        if (!$order->conekta_charge_id) {
            return false;
        }

        $webhookData = [
            'type' => 'charge.paid',
            'data' => [
                'object' => [
                    'id' => $order->conekta_charge_id,
                    'status' => 'paid',
                    'amount' => (int)($order->total * 100),
                    'currency' => 'MXN',
                    'customer_info' => [
                        'email' => $order->buyer_email,
                        'name' => $order->buyer_name,
                        'phone' => $order->buyer_phone,
                    ],
                    'payment_method' => ['type' => 'card'],
                    'metadata' => [
                        'external_reference' => $order->referencia_pago,
                    ],
                ],
            ],
        ];

        return $this->processWebhookNotification($webhookData);
    }
}
```

---

## Testing

### 1. Test Unitario

```php
<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\ConektaService;
use Tests\TestCase;

class ConektaServiceTest extends TestCase
{
    private ConektaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ConektaService::class);
    }

    public function test_create_checkout_session()
    {
        $data = [
            'title' => 'Test Event',
            'quantity' => 2,
            'unit_price' => 500,
            'payer_name' => 'Test User',
            'payer_email' => 'test@example.com',
            'payer_phone' => '5551234567',
            'external_reference' => 'TEST123',
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
        ];

        $result = $this->service->createCheckoutSession($data);

        // En ambiente de testing sin clave API
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no está configurado', $result['message']);
    }

    public function test_process_webhook_notification()
    {
        $order = Order::factory()->create([
            'conekta_charge_id' => 'ch_test_12345',
        ]);

        $webhookData = [
            'type' => 'charge.paid',
            'data' => [
                'object' => [
                    'id' => 'ch_test_12345',
                    'status' => 'paid',
                    'amount' => 150000,
                    'currency' => 'MXN',
                    'customer_info' => [
                        'email' => $order->buyer_email,
                    ],
                    'payment_method' => ['type' => 'card'],
                    'metadata' => [],
                ],
            ],
        ];

        $result = $this->service->processWebhookNotification($webhookData);

        $this->assertTrue($result);
        
        $order->refresh();
        $this->assertEquals('pagado', $order->estado_pago);
        $this->assertEquals('charge.paid', $order->conekta_event_type);
    }
}
```

### 2. Test de Integración

```php
<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventZone;
use Tests\TestCase;

class ConektaCheckoutTest extends TestCase
{
    public function test_create_conekta_checkout()
    {
        $event = Event::factory()->create(['estatus' => 'activo']);
        $zone = EventZone::factory()->create();

        $response = $this->postJson('/api/checkout/conekta', [
            'event_id' => $event->id,
            'event_zone_id' => $zone->id,
            'quantity' => 2,
            'buyer_name' => 'Test User',
            'buyer_email' => 'test@example.com',
            'buyer_phone' => '5551234567',
            'payment_method' => 'card',
        ]);

        // Sin API key configurada, debe retornar error
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Conekta no está configurado'
        ]);
    }
}
```

---

## cURL (Testing Manual)

### Crear Sesión de Checkout

```bash
curl -X POST http://localhost:8000/api/checkout/conekta \
  -H "Content-Type: application/json" \
  -d '{
    "event_id": 1,
    "event_zone_id": 5,
    "quantity": 2,
    "buyer_name": "Juan Pérez",
    "buyer_email": "juan@example.com",
    "buyer_phone": "5551234567",
    "payment_method": "card"
  }'
```

### Simular Webhook

```bash
curl -X POST http://localhost:8000/api/webhook/conekta \
  -H "Content-Type: application/json" \
  -d '{
    "type": "charge.paid",
    "data": {
      "object": {
        "id": "ch_test_12345",
        "status": "paid",
        "amount": 150000,
        "currency": "MXN",
        "customer_info": {
          "email": "juan@example.com",
          "name": "Juan Pérez",
          "phone": "5551234567"
        },
        "payment_method": {
          "type": "card"
        },
        "metadata": {
          "external_reference": "EV1-ZN5-Q2"
        }
      }
    }
  }'
```

---

## Monitoreo

### Ver Órdenes Pagadas con Conekta

```php
// En tinker o command
$orders = \App\Models\Order::where('estado_pago', 'pagado')
    ->whereNotNull('conekta_charge_id')
    ->latest()
    ->limit(10)
    ->get();

$orders->each(function($order) {
    echo sprintf(
        "Orden #%d: %s (%s)\n",
        $order->id,
        $order->conekta_charge_id,
        $order->conekta_event_type
    );
});
```

### Ver Webhooks Fallidos

```php
$failedWebhooks = \App\Models\WebhookLog::where('provider', 'conekta')
    ->where('status', 'failed')
    ->latest()
    ->limit(10)
    ->get();

$failedWebhooks->each(function($log) {
    echo sprintf(
        "Webhook #%d: %s - %s\n",
        $log->id,
        $log->event_type,
        $log->error ?? 'Sin error'
    );
});
```

---

## Referencias

- [Documentación oficial Conekta](https://developers.conekta.io)
- [Guía de setup](./SETUP_CONEKTA.md)
- [Documentación técnica](./TECNICO_CONEKTA.md)
