# Guía de Integración de Mercado Pago - LaReata Digital

## Resumen de la Integración

Se ha completado la integración de Mercado Pago para procesar pagos con tarjeta en tu plataforma de venta de boletos. La solución incluye:

- ✅ SDK de Mercado Pago (mercadopago/sdk-php)
- ✅ Servicio centralizado (MercadoPagoService)
- ✅ Controlador de Webhooks (WebhookController)
- ✅ Migración de base de datos para campos de transacción
- ✅ Rutas API configuradas
- ✅ CheckoutController mejorado

---

## Pasos de Configuración

### 1. Obtener Credenciales de Mercado Pago

1. Accede a tu dashboard de Mercado Pago
2. Ve a **Configuración** > **Credenciales**
3. Copia:
   - **Access Token** (Token de acceso)
   - **Public Key** (Clave pública)

### 2. Configurar Variables de Entorno

Agrega lo siguiente al archivo `.env`:

```bash
MERCADOPAGO_ACCESS_TOKEN=tu_access_token_aqui
MERCADOPAGO_PUBLIC_KEY=tu_public_key_aqui
```

También puedes usar las variables en `.env.example` que ya están preparadas.

### 3. Instalar Dependencias

```bash
composer require mercadopago/sdk-php
```

### 4. Ejecutar Migraciones

```bash
php artisan migrate
```

Esto agregará los campos necesarios a la tabla `ventas`:
- `mercadopago_transaction_id`: ID único de la transacción
- `mercadopago_preference_id`: ID de la preferencia de pago
- `mercadopago_payment_status`: Estado del pago en Mercado Pago
- `mercadopago_response`: JSON con la respuesta completa de Mercado Pago

---

## Flujo de Pago

### Opción 1: Checkout Web (Recomendado)

Este es el flujo más seguro. El cliente es redirigido al sitio de Mercado Pago.

#### Frontend: Crear Preferencia

```javascript
// Solicitar preferencia de pago al backend
const response = await fetch('/api/checkout/mercadopago/preference', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    event_id: 1,
    event_zone_id: 5,
    quantity: 2,
    buyer_name: 'Juan Pérez',
    buyer_email: 'juan@example.com',
    buyer_phone: '5551234567'
  })
});

const data = await response.json();
// Redirigir al usuario a data.redirect_url
window.location.href = data.redirect_url;
```

#### Backend: Respuesta

```json
{
  "redirect_url": "https://www.mercadopago.com.mx/checkout/v1/redirect?...",
  "preference_id": "1234567890"
}
```

### Opción 2: Webhook de Notificación

Mercado Pago enviará notificaciones a tu endpoint de webhook cuando el pago cambie de estado.

**Endpoint:** `POST /api/webhook/mercadopago`

#### Configurar URL de Webhook en Mercado Pago

1. Ve a **Cuenta** > **Configuración** > **Webhooks**
2. Agrega una nueva URL de webhook:
   ```
   https://tu-dominio.com/api/webhook/mercadopago
   ```
3. Selecciona eventos para "Pagos"

#### Flujo Automático

Cuando el cliente realiza un pago:

1. Mercado Pago procesa la transacción
2. Envía notificación a tu webhook
3. Tu aplicación actualiza automáticamente el estado de la orden
4. Se generan y envían los boletos por correo

---

## Estructura del Servicio MercadoPagoService

### Métodos Disponibles

#### 1. `createPreference(array $data): array`

Crea una preferencia de pago en Mercado Pago.

```php
$mercadoPagoService->createPreference([
    'title' => 'Concierto - Zona VIP',
    'quantity' => 2,
    'unit_price' => 500.00,
    'payer_name' => 'Juan Pérez',
    'payer_email' => 'juan@example.com',
    'payer_phone' => '5551234567',
    'external_reference' => 'ORDER-12345'
]);

// Respuesta exitosa:
// {
//     'success' => true,
//     'preference_id' => '123456789',
//     'init_point' => 'https://www.mercadopago.com.mx/...',
//     'sandbox_init_point' => 'https://sandbox.mercadopago.com.mx/...'
// }
```

#### 2. `getPaymentInfo(string $paymentId): ?array`

Obtiene información detallada de una transacción.

```php
$paymentInfo = $mercadoPagoService->getPaymentInfo('payment-id-123');

// Respuesta:
// {
//     'id' => '123456789',
//     'status' => 'approved',
//     'transaction_amount' => 1000.00,
//     'currency_id' => 'MXN',
//     'external_reference' => 'ORDER-12345',
//     'payer_email' => 'cliente@example.com',
//     'payment_method_id' => 'credit_card'
// }
```

#### 3. `processWebhookNotification(array $data): bool`

Procesa automáticamente las notificaciones del webhook.

---

## Flujos de Estado de Pago

Los pagos en Mercado Pago pueden tener los siguientes estados:

| Estado MP | Estado Local | Descripción |
|-----------|--------------|-------------|
| `approved` | `pagado` | Pago aprobado ✅ |
| `pending` | `pendiente` | Esperando confirmación ⏳ |
| `authorized` | `autorizado` | Autorizado, falta captura |
| `in_process` | `procesando` | Bajo revisión |
| `rejected` | `rechazado` | Pago rechazado ❌ |
| `cancelled` | `cancelado` | Pago cancelado |
| `refunded` | `reembolsado` | Reembolso realizado |
| `charged_back` | `devolución` | Devolución de cliente |

---

## API Endpoints

### Crear Preferencia de Pago

```
POST /api/checkout/mercadopago/preference
Content-Type: application/json

{
  "event_id": 1,
  "event_zone_id": 5,
  "quantity": 2,
  "buyer_name": "Juan Pérez",
  "buyer_email": "juan@example.com",
  "buyer_phone": "5551234567"
}
```

**Respuesta (200):**
```json
{
  "redirect_url": "https://www.mercadopago.com.mx/checkout/v1/redirect?...",
  "preference_id": "1234567890"
}
```

### Webhook de Mercado Pago

```
POST /api/webhook/mercadopago
```

Mercado Pago envía automáticamente notificaciones. No requiere autenticación.

### Endpoints de Retorno (auto-return)

Cuando `auto_return` está habilitado, después del pago Mercado Pago redirige a:

- **Éxito:** `GET /api/checkout/success?payment_id=xxx&preference_id=yyy`
- **Error:** `GET /api/checkout/failure?payment_id=xxx&preference_id=yyy`
- **Pendiente:** `GET /api/checkout/pending?payment_id=xxx&preference_id=yyy`

---

## Manejo de Errores

### Validación de Credenciales

El servicio verifica automáticamente que existan las credenciales:

```php
if (!$this->accessToken) {
    return [
        'success' => false,
        'message' => 'Mercado Pago no está configurado...'
    ];
}
```

### Excepciones

Todos los errores de Mercado Pago se registran en `storage/logs/laravel.log`:

```
[2026-05-03] Error al crear preferencia de Mercado Pago: {...}
```

---

## Seguridad

### Recomendaciones

1. **Nunca expongas el Access Token** en el frontend
2. **Usa HTTPS** en producción
3. **Valida todas las notificaciones** del webhook
4. **Guarda el JSON** de la respuesta de Mercado Pago
5. **Implementa reintentos** para webhooks fallidos

### Campos Protegidos

El modelo `Order` tiene los siguientes campos para auditoría:
- `mercadopago_transaction_id`: ID único de la transacción
- `mercadopago_response`: Respuesta JSON completa

---

## Ejemplos de Implementación

### Ejemplo 1: Flujo Completo de Compra

```php
// 1. El cliente envía datos de compra
POST /api/checkout/mercadopago/preference
{
  "event_id": 1,
  "event_zone_id": 5,
  "quantity": 2,
  "buyer_name": "María García",
  "buyer_email": "maria@example.com",
  "buyer_phone": "5559876543"
}

// 2. Servidor retorna URL de pago
Response 200:
{
  "redirect_url": "https://www.mercadopago.com.mx/...",
  "preference_id": "123456789"
}

// 3. Cliente es redirigido a Mercado Pago
// Cliente completa el pago con tarjeta

// 4. Mercado Pago envía webhook
POST /api/webhook/mercadopago
{
  "type": "payment",
  "data": {
    "id": "payment-123456789"
  }
}

// 5. Backend procesa webhook y actualiza orden
Order {
  id: 1,
  estado_pago: "pagado",
  mercadopago_transaction_id: "payment-123456789",
  mercadopago_payment_status: "approved"
}

// 6. Se envían boletos por correo automáticamente
```

### Ejemplo 2: Verificar Estado de una Orden

```php
use App\Models\Order;
use App\Services\MercadoPagoService;

$order = Order::find(1);

if ($order->mercadopago_transaction_id) {
    $service = app(MercadoPagoService::class);
    $paymentInfo = $service->getPaymentInfo($order->mercadopago_transaction_id);
    
    if ($paymentInfo['status'] === 'approved') {
        // Pago confirmado
    }
}
```

---

## Campos de Base de Datos

### Tabla `ventas`

Nuevos campos agregados:

```sql
ALTER TABLE ventas ADD COLUMN mercadopago_transaction_id VARCHAR(255) UNIQUE NULL;
ALTER TABLE ventas ADD COLUMN mercadopago_preference_id VARCHAR(255) NULL;
ALTER TABLE ventas ADD COLUMN mercadopago_payment_status VARCHAR(50) NULL;
ALTER TABLE ventas ADD COLUMN mercadopago_response JSON NULL;
```

---

## Testing en Sandbox

Para probar sin dinero real:

### Tarjetas de Prueba

**Tarjeta Aprobada:**
- Número: `4111111111111111`
- Fecha: `11/25`
- CVV: `123`

**Tarjeta Rechazada:**
- Número: `5105105105105100`
- Fecha: `11/25`
- CVV: `123`

### Datos de Prueba

- Email: Cualquier email válido
- Documento: `12345678` (cualquier número)
- Teléfono: Cualquier número

---

## Monitoreo y Logs

Todos los eventos se registran en `storage/logs/laravel.log`:

```
[2026-05-03 10:15:30] Webhook de Mercado Pago recibido: type=payment, data.id=123
[2026-05-03 10:15:31] Orden actualizada: order_id=1, payment_id=123, status=approved
```

---

## Solución de Problemas

### Problema: "Mercado Pago no configurado"

**Solución:** Verifica que `MERCADOPAGO_ACCESS_TOKEN` esté en `.env`

```bash
echo $MERCADOPAGO_ACCESS_TOKEN
```

### Problema: Webhook no se recibe

**Solución:**
1. Verifica URL en dashboard de Mercado Pago
2. Usa `ngrok` para testing local
3. Revisa logs en `storage/logs/laravel.log`

### Problema: Pago rechazado en Sandbox

**Solución:**
1. Usa tarjeta de prueba `5105105105105100`
2. Verifica documento sea válido (no necesita serlo en sandbox)
3. Revisa estado en dashboard de Mercado Pago

---

## Próximos Pasos

1. **Implementar manejo de reembolsos:**
   ```php
   $service->refundPayment($paymentId, $amount);
   ```

2. **Agregar pagos recurrentes:** Para suscripciones

3. **Mejorar notificaciones:** Enviar estados por SMS

4. **Dashboard:** Ver transacciones en panel de admin

---

## Contacto y Soporte

Para más información:
- [Documentación de Mercado Pago](https://developers.mercadopago.com/es/docs/checkout-pro/landing)
- [SDK PHP de Mercado Pago](https://github.com/mercadopago/sdk-php)
