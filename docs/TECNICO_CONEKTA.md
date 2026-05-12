# Integración de Conekta - Guía Técnica Completa

## 📋 Tabla de Contenidos

1. [Arquitectura](#arquitectura)
2. [Configuración](#configuración)
3. [Métodos API](#métodos-api)
4. [Webhooks](#webhooks)
5. [Flujos de Pago](#flujos-de-pago)
6. [Manejo de Errores](#manejo-de-errores)
7. [Base de Datos](#base-de-datos)

---

## Arquitectura

### Componentes

```
ConektaService
    ├── createCheckoutSession() → Crear sesión de pago
    ├── getChargeInfo() → Obtener info de cargo
    ├── processWebhookNotification() → Procesar webhook
    └── updateOrderFromWebhookEvent() → Actualizar orden

CheckoutController
    └── createConektaCheckout() → Endpoint del cliente

WebhookController
    ├── conekta() → Webhook handler
    ├── conektaSuccess() → Redirect success
    └── conektaFailure() → Redirect failure
```

### Flujo de Pagos

```
Cliente
  ↓
POST /api/checkout/conekta
  ↓
CheckoutController::createConektaCheckout()
  ↓
ConektaService::createCheckoutSession()
  ↓
API Conekta (crear sesión)
  ↓
Respuesta: checkout_url
  ↓
Redirigir a https://pay.conekta.io/checkout/...
  ↓
Cliente paga
  ↓
Webhook: POST /api/webhook/conekta
  ↓
WebhookController::conekta()
  ↓
ConektaService::processWebhookNotification()
  ↓
Actualizar orden en BD
  ↓
Disparar evento PaymentReceived
```

---

## Configuración

### Variables de Entorno

```bash
# .env
CONEKTA_API_KEY=key_live_XXXXXXXXXXXXXXXXXXXX
CONEKTA_WEBHOOK_URL=https://tudominio.com/api/webhook/conekta
```

### Configuración en config/services.php

```php
'conekta' => [
    'api_key' => env('CONEKTA_API_KEY'),
    'webhook_url' => env('CONEKTA_WEBHOOK_URL', env('APP_URL').'/api/webhook/conekta'),
],
```

### Modelos

```php
// Order model - tabla 'ventas'
protected $fillable = [
    'conekta_session_id',
    'conekta_charge_id',
    'conekta_event_type',
    'conekta_response',
    // ... otros campos
];
```

---

## Métodos API

### ConektaService::createCheckoutSession()

**Propósito:** Crear una nueva sesión de checkout en Conekta

**Parámetros:**
```php
$data = [
    'title' => 'Evento - Zona A',
    'description' => 'Compra de 2 boletos',
    'quantity' => 2,
    'unit_price' => 500.00, // en MXN
    'payer_name' => 'Juan Pérez',
    'payer_email' => 'juan@example.com',
    'payer_phone' => '5551234567',
    'external_reference' => 'EV1-ZN5-Q2',
    'success_url' => 'https://tudominio.com/compra?success',
    'failure_url' => 'https://tudominio.com/compra?failure',
];
```

**Retorna:**
```php
[
    'success' => true,
    'session_id' => 'ses_2nCcd2EG0WVE3w',
    'checkout_url' => 'https://pay.conekta.io/checkout/...',
    'expires_at' => 1715000000,
]
// O en caso de error:
[
    'success' => false,
    'message' => 'Error al crear sesión de pago',
    'details' => [...],
]
```

**Detalles Técnicos:**
- Precios en **centavos**: unit_price * 100
- Moneda: **MXN** (mexicano)
- Expiración: **24 horas**
- Auth: **Basic Auth** (API Key + vacío)

**Ejemplo:**
```php
$service = app(ConektaService::class);
$result = $service->createCheckoutSession([
    'title' => 'Evento Concierto 2025',
    'quantity' => 3,
    'unit_price' => 750.00,
    'payer_name' => 'María García',
    'payer_email' => 'maria@example.com',
    'payer_phone' => '5559876543',
    'external_reference' => 'ORDER123',
    'success_url' => 'https://app.example.com/success',
    'failure_url' => 'https://app.example.com/failure',
]);

if ($result['success']) {
    // Guardar session_id en BD
    // Redirigir a $result['checkout_url']
}
```

---

### ConektaService::getChargeInfo()

**Propósito:** Obtener información detallada de un cargo

**Parámetros:**
```php
$chargeId = 'ch_12345678901234567890';
$info = $service->getChargeInfo($chargeId);
```

**Retorna:**
```php
[
    'id' => 'ch_12345678901234567890',
    'status' => 'paid', // paid, pending, expired, failed
    'amount' => 150000, // en centavos
    'currency' => 'MXN',
    'payment_method' => 'card', // card, oxxo, bank_transfer, etc
    'customer_email' => 'juan@example.com',
    'metadata' => [
        'external_reference' => 'EV1-ZN5-Q2',
    ],
]
// O null si hay error
```

---

### ConektaService::processWebhookNotification()

**Propósito:** Procesar notificaciones de webhooks de Conekta

**Tipos de eventos soportados:**
- `charge.paid` → Pago completado
- `charge.pending` → Pago pendiente
- `charge.expired` → Sesión expirada
- `charge.failed` → Pago fallido

**Datos del webhook:**
```json
{
  "type": "charge.paid",
  "data": {
    "object": {
      "id": "ch_12345678901234567890",
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
}
```

---

## Webhooks

### Configuración

1. Panel Conekta: https://panel.conekta.io/developers/webhooks
2. URL: `https://tudominio.com/api/webhook/conekta`
3. Eventos:
   - ✅ charge.paid
   - ✅ charge.pending
   - ✅ charge.expired
   - ✅ charge.failed

### Validación de Webhooks

```php
// Conekta envía headers de validación
// Por ahora, se confía en el HTTPS y la IP de Conekta
// Implementación futura:
if (!$this->conektaService->validateWebhookSignature($headers, $body)) {
    return response()->json(['error' => 'Firma inválida'], 401);
}
```

### Procesamiento de Webhooks

```php
// WebhookController::conekta()
$data = $request->json()->all();
$success = $this->conektaService->processWebhookNotification($data);

// La orden se actualiza automáticamente:
// - conekta_charge_id
// - conekta_event_type
// - estado_pago (pagado, pendiente, rechazado, expirado)
// - conekta_response (JSON completo)
```

### Logs de Webhooks

Los webhooks se registran en la tabla `webhook_logs`:

```php
// Ver logs
Route::get('/api/admin/webhook-logs', [WebhookLogController::class, 'index']);
Route::get('/api/admin/webhook-logs/{id}', [WebhookLogController::class, 'show']);
Route::post('/api/admin/webhook-logs/{id}/retry', [WebhookLogController::class, 'retry']);
```

---

## Flujos de Pago

### Flujo 1: Pago Exitoso (Happy Path)

```
1. Cliente POST /api/checkout/conekta
   ↓
2. Crear sesión en Conekta
   ↓
3. Redirigir a pay.conekta.io/checkout/...
   ↓
4. Cliente completa pago (tarjeta, OXXO, etc)
   ↓
5. Webhook: charge.paid
   ↓
6. WebhookController actualiza orden
   ↓
7. estado_pago = 'pagado'
   ↓
8. Event PaymentReceived se dispara
   ↓
9. Emails/Boletos se envían
```

### Flujo 2: Pago Pendiente

```
1. Cliente inicia pago (ej: OXXO, transferencia)
   ↓
2. Webhook: charge.pending
   ↓
3. estado_pago = 'pendiente'
   ↓
4. Cliente ve "En espera de confirmación"
   ↓
5. (Después de 48-72h, confirmación o expiración)
```

### Flujo 3: Sesión Expirada

```
1. Cliente no completa pago en 24 horas
   ↓
2. Webhook: charge.expired
   ↓
3. estado_pago = 'expirado'
   ↓
4. Orden permanece pendiente
```

### Flujo 4: Pago Rechazado

```
1. Cliente intenta pago
   ↓
2. Conekta rechaza la tarjeta (fondos insuficientes, etc)
   ↓
3. Webhook: charge.failed
   ↓
4. estado_pago = 'rechazado'
   ↓
5. Cliente ve error y puede reintentar
```

---

## Manejo de Errores

### Errores en Creación de Sesión

```php
// Error: Credenciales no configuradas
$result = [
    'success' => false,
    'message' => 'Conekta no está configurado. Falta CONEKTA_API_KEY.',
];

// Error: API de Conekta rechaza
$result = [
    'success' => false,
    'message' => 'Error al crear sesión de pago',
    'details' => [
        'status' => 400,
        'body' => ['error' => 'Invalid amount'],
    ],
];
```

### Errores en Webhooks

```php
// Se registra en webhook_logs con status = 'failed'
WebhookLog::create([
    'provider' => 'conekta',
    'event_type' => 'charge.paid',
    'payload' => $data,
    'status' => 'failed',
    'error' => 'No se encontró orden asociada',
]);

// Se puede reintentar:
// POST /api/admin/webhook-logs/{id}/retry
```

### Logging

Todos los eventos se registran en `storage/logs/laravel.log`:

```
[2025-05-11 10:30:00] local.INFO: Webhook de Conekta recibido type="charge.paid" data.id="ch_12345"
[2025-05-11 10:30:01] local.INFO: Orden actualizada con información de Conekta order_id=42 charge_id="ch_12345" event_type="charge.paid"
```

---

## Base de Datos

### Tabla: ventas

Campos nuevos de Conekta:

```sql
ALTER TABLE ventas ADD COLUMN conekta_session_id VARCHAR(255) UNIQUE NULLABLE;
ALTER TABLE ventas ADD COLUMN conekta_charge_id VARCHAR(255) UNIQUE NULLABLE;
ALTER TABLE ventas ADD COLUMN conekta_event_type VARCHAR(50) NULLABLE;
ALTER TABLE ventas ADD COLUMN conekta_response JSON NULLABLE;

CREATE INDEX idx_conekta_session_id ON ventas(conekta_session_id);
CREATE INDEX idx_conekta_charge_id ON ventas(conekta_charge_id);
CREATE INDEX idx_conekta_event_type ON ventas(conekta_event_type);
```

### Tabla: webhook_logs

Ejemplo de registro:

```json
{
  "id": 1,
  "provider": "conekta",
  "event_type": "charge.paid",
  "payload": {
    "type": "charge.paid",
    "data": {
      "object": {
        "id": "ch_12345",
        "status": "paid",
        "amount": 150000
      }
    }
  },
  "order_id": 42,
  "status": "success",
  "response": {
    "order_id": 42,
    "event_type": "charge.paid"
  },
  "created_at": "2025-05-11 10:30:01"
}
```

---

## Monitoreo y Debugging

### Verificar Credenciales

```php
// Terminal
$service = app(\App\Services\ConektaService::class);
$info = $service->getChargeInfo('ch_test_12345'); // Debe retornar null (no existe)
// Si retorna error de autenticación, revisar CONEKTA_API_KEY
```

### Ver Logs Recientes

```bash
tail -f storage/logs/laravel.log | grep -i conekta
```

### Ver Órdenes con Conekta

```php
// Tinker
$orders = \App\Models\Order::whereNotNull('conekta_charge_id')->get();
$orders->each(fn($o) => dd($o->conekta_response));
```

### Tabla de Referencia Rápida

| Campo BD | Tipo | Fuente |
|----------|------|--------|
| `conekta_session_id` | string | Response de createCheckoutSession |
| `conekta_charge_id` | string | Webhook data.object.id |
| `conekta_event_type` | string | Webhook type |
| `conekta_response` | json | Webhook completo |

---

## 🔗 Referencias

- [API Conekta - Crear Sesión](https://developers.conekta.io/reference/create-checkout-session)
- [API Conekta - Webhooks](https://developers.conekta.io/reference/webhooks)
- [Laravel HTTP Client](https://laravel.com/docs/http-client)
