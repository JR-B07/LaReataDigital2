# Configuración Técnica - Mercado Pago

## 📋 Resumen de Cambios

### Archivos Creados

1. **app/Services/MercadoPagoService.php**
   - Servicio centralizado para todas las operaciones con Mercado Pago
   - Maneja preferencias, pagos y webhooks

2. **app/Http/Controllers/Api/WebhookController.php**
   - Recibe y procesa notificaciones de Mercado Pago
   - Maneja endpoints de retorno

3. **database/migrations/2026_05_03_000000_add_mercadopago_fields_to_ventas_table.php**
   - Agrega campos de transacción a tabla `ventas`

4. **docs/MERCADOPAGO_INTEGRATION.md**
   - Guía completa de integración

5. **docs/MERCADOPAGO_EJEMPLOS.md**
   - Ejemplos de código para frontend

6. **docs/SETUP_MERCADOPAGO.md**
   - Guía rápida de setup

### Archivos Modificados

1. **composer.json**
   - Agregada dependencia: `mercadopago/sdk-php: ^3.0`

2. **routes/api.php**
   - Importada nueva clase `WebhookController`
   - Agregadas rutas de webhook y retorno

3. **app/Models/Order.php**
   - Agregados nuevos campos al `$fillable`

4. **app/Http/Controllers/Api/CheckoutController.php**
   - Inyectado `MercadoPagoService`
   - Refactorizado método `createMercadoPagoPreference`

---

## 📦 Dependencias

### Agregada al composer.json
```json
"mercadopago/sdk-php": "^3.0"
```

### Instalación

```bash
composer install
```

---

## 🗄️ Base de Datos

### Nueva Migración

Archivo: `database/migrations/2026_05_03_000000_add_mercadopago_fields_to_ventas_table.php`

Campos agregados:

```sql
ALTER TABLE ventas ADD COLUMN mercadopago_transaction_id VARCHAR(255) UNIQUE NULL AFTER referencia_pago;
ALTER TABLE ventas ADD COLUMN mercadopago_preference_id VARCHAR(255) NULL AFTER mercadopago_transaction_id;
ALTER TABLE ventas ADD COLUMN mercadopago_payment_status VARCHAR(50) NULL AFTER mercadopago_preference_id;
ALTER TABLE ventas ADD COLUMN mercadopago_response JSON NULL AFTER mercadopago_payment_status;

CREATE INDEX idx_mercadopago_transaction_id ON ventas(mercadopago_transaction_id);
CREATE INDEX idx_mercadopago_preference_id ON ventas(mercadopago_preference_id);
```

### Ejecución

```bash
php artisan migrate
```

---

## 🔐 Variables de Entorno

### Agregar a `.env`

```bash
# Mercado Pago
MERCADOPAGO_ACCESS_TOKEN=APP_USR-XXXXXXXXXXXXXXXX-XXXXXXXX-XXXXXXXXXXXXXXXX
MERCADOPAGO_PUBLIC_KEY=APP_USR-XXXXXXXXXXXXXXXX-XXXXXXXX
```

### Obtener Credenciales

1. Ve a: https://www.mercadopago.com.mx/developers/es
2. Login con tu cuenta
3. Ve a **Configuración** > **Credenciales**
4. Copia **Access Token** y **Public Key**

### Modo Sandbox

Para testing, usa tokens de sandbox:
- Access Token: `APP_USR-...` (si empieza con TEST_, es sandbox)
- Válido automáticamente en desarrollo

---

## 🏗️ Arquitectura de Servicios

### MercadoPagoService

**Ubicación:** `app/Services/MercadoPagoService.php`

**Métodos públicos:**

```php
// Crear preferencia de pago
createPreference(array $data): array

// Obtener información de pago
getPaymentInfo(string $paymentId): ?array

// Procesar webhook
processWebhookNotification(array $data): bool

// Validar firma (para futura implementación)
validateWebhookSignature(array $headers, string $body): bool
```

**Inyección:**

```php
public function __construct(private readonly MercadoPagoService $mercadoPagoService) {}
```

---

## 🔌 Rutas API

### Nuevas Rutas

```
POST   /api/checkout/mercadopago/preference    - Crear preferencia
POST   /api/webhook/mercadopago                - Webhook de MP
GET    /api/checkout/success                   - Retorno éxito
GET    /api/checkout/failure                   - Retorno fallo
GET    /api/checkout/pending                   - Retorno pendiente
```

### Rutas sin Autenticación

Todas las rutas de webhook y retorno **NO** requieren autenticación, ya que son llamadas por Mercado Pago o redireccionamiento del navegador.

---

## 📊 Flujo de Datos

### Creación de Preferencia

```
Frontend                Backend                Mercado Pago
   |                      |                         |
   |--POST /preference-----→                       |
   |                      |                         |
   |                      |--POST /checkout/prefs--→
   |                      |                         |
   |                      |←--{init_point}--------|
   |--redirect to init_point--→ (Usuario paga)
   |                      |                         |
```

### Procesamiento de Pago

```
Mercado Pago           Backend                  BD
   |                     |                       |
   |--POST /webhook------→                       |
   |                     |                       |
   |                     |--getPaymentInfo()-----→ MP
   |                     |←--{payment_info}------|
   |                     |                       |
   |                     |--Update Order---------→
   |                     |←--Success---------|
   |←--200 OK-----------|
```

---

## 🔄 Estados de Pago

### Mapeo: Estado Mercado Pago → Estado Local

| MP Status | Local Status | Acción |
|-----------|-------------|--------|
| approved | pagado | ✅ Generar boletos |
| pending | pendiente | ⏳ Esperar confirmación |
| authorized | autorizado | 🔒 Esperar captura |
| in_process | procesando | ⚙️ En revisión |
| rejected | rechazado | ❌ Notificar cliente |
| cancelled | cancelado | ⛔ Cancelado |
| refunded | reembolsado | 💰 Reembolso |
| charged_back | devolución | 🔄 Devolución cliente |

---

## 🧪 Testing Local

### Con ngrok (para webhooks locales)

```bash
# Terminal 1: Crear túnel
ngrok http 8000

# Copia la URL generada (ej: https://xxxx-xx-xxx-xx-xx.ngrok.io)

# Terminal 2: Laravel
php artisan serve

# Configurar en Mercado Pago:
# Webhook URL: https://xxxx-xx-xxx-xx-xx.ngrok.io/api/webhook/mercadopago
```

### Sin ngrok (solo checkout manual)

```bash
# Terminal 1
php artisan serve

# Ve a: http://localhost:8000/api/checkout/mercadopago/preference
# Con POST body:
{
  "event_id": 1,
  "event_zone_id": 5,
  "quantity": 2,
  "buyer_name": "Test",
  "buyer_email": "test@example.com",
  "buyer_phone": "5551234567"
}
```

---

## 📝 Ejemplo de Respuesta de Preferencia

### Request

```json
POST /api/checkout/mercadopago/preference

{
  "event_id": 1,
  "event_zone_id": 5,
  "quantity": 2,
  "buyer_name": "María García",
  "buyer_email": "maria@example.com",
  "buyer_phone": "5551234567"
}
```

### Response (Éxito)

```json
{
  "redirect_url": "https://www.mercadopago.com.mx/checkout/v1/redirect?pref_id=1234567890&token=TOKEN",
  "preference_id": "1234567890"
}
```

### Response (Error)

```json
{
  "message": "Error al crear preferencia de pago",
  "details": "Invalid access token"
}
```

---

## 📝 Ejemplo de Webhook

### Request

```json
POST /api/webhook/mercadopago

{
  "id": "12345",
  "type": "payment",
  "data": {
    "id": "payment-123456789"
  }
}
```

### Response

```json
{
  "success": true,
  "message": "Notificación procesada"
}
```

---

## 🔍 Logs

### Ubicación

`storage/logs/laravel.log`

### Eventos Registrados

```
[2026-05-03 10:15:30] Info: Webhook de Mercado Pago recibido: type=payment
[2026-05-03 10:15:31] Info: Orden actualizada: order_id=1, payment_id=123, status=approved
[2026-05-03 10:15:32] Error: No se encontró orden para pago de Mercado Pago
```

### Monitoreo

```bash
# Ver logs en tiempo real
tail -f storage/logs/laravel.log

# Filtrar por Mercado Pago
grep -i "mercadopago" storage/logs/laravel.log
```

---

## 🔐 Seguridad

### Implementado

✅ Access Token en `.env` (no en código)
✅ HTTPS obligatorio en producción
✅ Validación de datos de entrada
✅ Logging de transacciones
✅ Manejo de excepciones
✅ Campos JSON para auditoria

### Recomendaciones

1. **Nunca expongas el Access Token** en respuestas o logs
2. **Usa HTTPS** en producción
3. **Valida todas las notificaciones** del webhook
4. **Implementa reintentos** para webhooks fallidos
5. **Usa IP whitelist** si es posible

---

## 🚀 Despliegue a Producción

### Checklist

- [ ] Credenciales de producción en `.env`
- [ ] HTTPS habilitado
- [ ] Webhook URL configurada en dashboard
- [ ] Base de datos migrada
- [ ] Logs configurados
- [ ] Email de confirmación funcionando
- [ ] Prueba con tarjeta real (monto pequeño)

### Cambiar de Sandbox a Producción

Solo reemplaza el token en `.env`:

```bash
# Sandbox (Testing)
MERCADOPAGO_ACCESS_TOKEN=APP_USR-TEST-xxxx-xxxx

# Producción
MERCADOPAGO_ACCESS_TOKEN=APP_USR-xxxx-xxxx
```

---

## 📞 Contacto Mercado Pago

- **Documentación:** https://developers.mercadopago.com/es
- **API Reference:** https://developers.mercadopago.com/es/reference
- **Status:** https://status.mercadopago.com/
- **Soporte:** https://www.mercadopago.com.mx/ayuda

---

## 📋 Checklist de Implementación

- [x] SDK instalado en composer.json
- [x] Servicio MercadoPagoService creado
- [x] WebhookController creado
- [x] Migraciones de BD creadas
- [x] Rutas configuradas
- [x] Modelo Order actualizado
- [x] CheckoutController mejorado
- [ ] Testing con tarjeta de prueba
- [ ] Webhook configurado en dashboard
- [ ] Despliegue a producción
