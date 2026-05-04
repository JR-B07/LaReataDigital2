# Actualización de Integración Mercado Pago - Fase 2

## Resumen de Nuevos Componentes

Se ha completado la segunda fase de la integración de Mercado Pago con componentes avanzados para producción.

### ✨ Nuevas Características

1. **Sistema de Eventos** - Disparar eventos cuando se reciben pagos
2. **Listeners de Eventos** - Procesar eventos de forma asincrónica
3. **Historial de Webhooks** - Registrar todos los webhooks recibidos
4. **Reintentos Automáticos** - Artisan command para reintentar webhooks fallidos
5. **Panel de Admin** - Visualizar y gestionar webhooks
6. **Validación de Entrada** - FormRequest para webhooks

---

## Componentes Creados

### 1. FormRequest
**Archivo:** `app/Http/Requests/MercadoPagoWebhookRequest.php`

Valida automáticamente que los webhooks tengan la estructura correcta.

```php
// Uso en WebhookController
public function mercadopago(MercadoPagoWebhookRequest $request)
{
    $data = $request->validated(); // Datos ya validados
}
```

### 2. Evento PaymentReceived
**Archivo:** `app/Events/PaymentReceived.php`

Se dispara cuando se recibe y procesa un pago exitosamente.

```php
PaymentReceived::dispatch($order, $paymentInfo, $paymentId);
```

### 3. Listener SendTicketsAfterPayment
**Archivo:** `app/Listeners/SendTicketsAfterPayment.php`

Escucha el evento `PaymentReceived` y envía automáticamente los boletos por email.

```php
// Registrado en AppServiceProvider
$this->app['events']->listen(
    PaymentReceived::class,
    SendTicketsAfterPayment::class,
);
```

### 4. Modelo WebhookLog
**Archivo:** `app/Models/WebhookLog.php`

Registra todo webhook recibido con su estado y respuesta.

**Campos:**
- `type` - Tipo de notificación (payment, merchant_order, etc.)
- `resource_id` - ID del recurso (payment ID)
- `order_id` - Orden asociada (si existe)
- `status` - Estado (pending, processing, success, failed)
- `payload` - JSON con datos completos del webhook
- `response` - Respuesta generada
- `error_message` - Mensaje de error si falló
- `retry_count` - Número de reintentos
- `processed_at` - Fecha de procesamiento

**Scopes útiles:**
```php
WebhookLog::pending()   // Pendientes
WebhookLog::failed()    // Fallidos
WebhookLog::success()   // Exitosos
```

### 5. Migración de WebhookLog
**Archivo:** `database/migrations/2026_05_03_000001_create_mercadopago_webhook_logs_table.php`

Crear tabla `mercadopago_webhook_logs`.

### 6. Comando Artisan
**Archivo:** `app/Console/Commands/ProcessMercadoPagoWebhooks.php`

Procesar webhooks pendientes o reintentar fallidos.

```bash
# Procesar webhooks pendientes
php artisan mercadopago:process-webhooks

# Reintentar webhooks fallidos (máximo 3 reintentos)
php artisan mercadopago:process-webhooks --retry-failed
```

### 7. Controlador WebhookLogController
**Archivo:** `app/Http/Controllers/Api/Admin/WebhookLogController.php`

Panel de administración para ver y gestionar webhooks.

**Endpoints:**
- `GET /api/admin/webhook-logs` - Listar webhooks
- `GET /api/admin/webhook-logs/{id}` - Ver detalles
- `POST /api/admin/webhook-logs/{id}/retry` - Reintentar webhook
- `GET /api/admin/webhook-logs-stats` - Estadísticas
- `GET /api/admin/webhook-logs-summary` - Resumen por tipo

---

## Flujo Mejorado de Procesamiento de Pagos

```
Mercado Pago
    ↓
POST /api/webhook/mercadopago
    ↓
FormRequest Validation
    ↓
MercadoPagoService::processWebhookNotification()
    ↓
┌─ Registrar en WebhookLog (pending)
│
├─ Marcar como processing
│
├─ Obtener información de pago
│
├─ Buscar orden asociada
│
├─ Actualizar estado de orden
│
├─ Disparar evento PaymentReceived
│
├─ SendTicketsAfterPayment (Listener)
│  └─ Generar PDFs
│  └─ Enviar email con boletos
│
└─ Marcar WebhookLog como success
```

---

## Uso del Sistema de Eventos

### 1. Escuchar Evento de Pago

```php
// En cualquier listener
use App\Events\PaymentReceived;

class MyCustomListener implements ShouldQueue
{
    public function handle(PaymentReceived $event)
    {
        $order = $event->order;
        $paymentInfo = $event->paymentInfo;
        $paymentId = $event->paymentId;
        
        // Hacer algo cuando se recibe un pago
    }
}
```

### 2. Registrar Listener

```php
// En AppServiceProvider
use App\Events\PaymentReceived;
use App\Listeners\MyCustomListener;

public function boot()
{
    $this->app['events']->listen(
        PaymentReceived::class,
        MyCustomListener::class,
    );
}
```

---

## Panel de Admin para Webhooks

### Acceder a Webhooks
```
GET /api/admin/webhook-logs
```

**Parámetros opcionales:**
- `type` - Filtrar por tipo (payment, merchant_order, etc.)
- `status` - Filtrar por estado (pending, success, failed, processing)
- `from_date` - Fecha inicial (YYYY-MM-DD)
- `to_date` - Fecha final (YYYY-MM-DD)

**Respuesta:**
```json
{
  "data": [
    {
      "id": 1,
      "type": "payment",
      "resource_id": "123456789",
      "order_id": 1,
      "status": "success",
      "payload": {...},
      "response": {...},
      "retry_count": 0,
      "processed_at": "2026-05-03T10:15:31Z",
      "created_at": "2026-05-03T10:15:30Z",
      "order": {...}
    }
  ],
  "pagination": {...}
}
```

### Ver Estadísticas
```
GET /api/admin/webhook-logs-stats
```

**Respuesta:**
```json
{
  "total": 150,
  "success": 145,
  "failed": 3,
  "pending": 2,
  "success_rate": 96.67
}
```

### Ver Resumen por Tipo
```
GET /api/admin/webhook-logs-summary
```

**Respuesta:**
```json
{
  "payment": {
    "pending": 0,
    "processing": 0,
    "success": 145,
    "failed": 3
  },
  "merchant_order": {
    "success": 50
  }
}
```

### Reintentar Webhook Fallido
```
POST /api/admin/webhook-logs/{id}/retry
```

---

## Reintentos Automáticos

### Comando Manual
```bash
# Una vez
php artisan mercadopago:process-webhooks

# Con reintentos (máx 3 por webhook)
php artisan mercadopago:process-webhooks --retry-failed
```

### Automatizar con Scheduler
```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // Procesar webhooks pendientes cada 5 minutos
    $schedule->command('mercadopago:process-webhooks')
        ->everyFiveMinutes()
        ->withoutOverlapping();
    
    // Reintentar webhooks fallidos cada hora
    $schedule->command('mercadopago:process-webhooks --retry-failed')
        ->hourly()
        ->withoutOverlapping();
}
```

---

## Instalación y Setup

### 1. Ejecutar Migraciones
```bash
php artisan migrate
```

Esto crea la tabla `mercadopago_webhook_logs`.

### 2. Registrar Listener
Ya está registrado en `AppServiceProvider::boot()`.

### 3. Probar

```bash
# Simular webhook
curl -X POST http://localhost:8000/api/webhook/mercadopago \
  -H "Content-Type: application/json" \
  -d '{
    "type": "payment",
    "data": {
      "id": "12345"
    }
  }'

# Ver logs
tail -f storage/logs/laravel.log

# Ver webhooks registrados
php artisan tinker
>>> App\Models\WebhookLog::latest()->first()
```

---

## Tratamiento de Errores

### Si un webhook falla:

1. **Se registra en WebhookLog** con status `failed`
2. **Se incrementa retry_count**
3. **Se guarda mensaje de error**
4. **El Listener reintenta automáticamente** si tiene `ShouldQueue`
5. **Command artisan reintentar manualmente** si es necesario

### Límites de Reintento

- **Máximo 3 reintentos** automáticamente con el command
- **Sin límite** si está en queue (depende de configuración)
- **Válido si retry_count < 5** vía API

---

## Logging Mejorado

### Eventos Registrados

```
[2026-05-03 10:15:30] Info: Webhook de Mercado Pago recibido: type=payment, data.id=123
[2026-05-03 10:15:31] Info: Orden actualizada: order_id=1, status=approved
[2026-05-03 10:15:32] Info: Boletos enviados después de pago aprobado: order_id=1
[2026-05-03 10:15:33] Error: Error enviando boletos: SMTP connection failed
[2026-05-03 10:15:34] Info: Webhook log marcado como failed: retry_count=1
```

### Ver Logs de Webhooks
```bash
# Filtrar por Mercado Pago
grep -i "webhook\|mercadopago" storage/logs/laravel.log

# Últimas líneas en tiempo real
tail -f storage/logs/laravel.log | grep -i "mercadopago"
```

---

## Modelos Mejorados

### Order (Modelo)
Ahora tiene soporte completo para transacciones de Mercado Pago:
- `mercadopago_transaction_id`
- `mercadopago_preference_id`
- `mercadopago_payment_status`
- `mercadopago_response` (JSON con respuesta completa)

### WebhookLog (Nuevo)
Registra cada webhook recibido con su procesamiento y resultado.

---

## Rutas Nuevas

```
GET    /api/admin/webhook-logs              - Listar webhooks
GET    /api/admin/webhook-logs/{id}         - Ver detalles
POST   /api/admin/webhook-logs/{id}/retry   - Reintentar
GET    /api/admin/webhook-logs-stats        - Estadísticas
GET    /api/admin/webhook-logs-summary      - Resumen por tipo
```

---

## Checklist de Implementación

- [x] FormRequest creada
- [x] Evento PaymentReceived creado
- [x] Listener SendTicketsAfterPayment creado
- [x] Modelo WebhookLog creado
- [x] Migración de WebhookLog creada
- [x] Command ProcessMercadoPagoWebhooks creado
- [x] WebhookLogController creado
- [x] Rutas agregadas
- [x] AppServiceProvider actualizado
- [x] MercadoPagoService mejorado
- [x] WebhookController mejorado
- [ ] Ejecutar migraciones
- [ ] Probar en sandbox
- [ ] Configurar scheduler si es necesario
- [ ] Desplegar a producción

---

## Próximas Mejoras

1. **Reembolsos** - Implementar refunds desde admin
2. **Notificaciones SMS** - Alertar sobre estado de pagos
3. **Dashboard Gráfico** - Dashboard visual de webhooks
4. **Exportar Reportes** - Descargar logs de webhooks
5. **Integración con Slack** - Alertas en Slack
6. **Metricas** - Prometheus metrics para monitoring

---

## Contacto y Soporte

Ver documentación anterior:
- `docs/MERCADOPAGO_INTEGRATION.md`
- `docs/SETUP_MERCADOPAGO.md`

---

**Actualización:** 3 de mayo de 2026
**Versión:** 2.0 (Enhanced Release)
