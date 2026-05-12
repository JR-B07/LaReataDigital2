# Integración de Conekta - Guía Completa

## 📋 Tabla de Contenidos

1. [Introducción](#introducción)
2. [Configuración Inicial](#configuración-inicial)
3. [Flujos de Integración](#flujos-de-integración)
4. [Métodos de Pago](#métodos-de-pago)
5. [Gestión de Webhooks](#gestión-de-webhooks)
6. [Estados de Pago](#estados-de-pago)
7. [Testing](#testing)
8. [Troubleshooting](#troubleshooting)

---

## Introducción

Conekta es una plataforma de pagos mexicana que permite procesar:
- 🎴 Tarjetas de crédito/débito
- 🏪 OXXO
- 🏦 Transferencias bancarias
- 📱 Google Pay, Apple Pay
- 💳 PayPal

Esta integración permite que los usuarios compren boletos en LaReata Digital usando múltiples métodos de pago.

---

## Configuración Inicial

### 1. Crear Cuenta en Conekta

1. Ir a https://panel.conekta.io
2. Registrarse con email y contraseña
3. Verificar identidad
4. Obtener claves API

### 2. Configurar Variables de Entorno

```bash
# .env
CONEKTA_API_KEY=key_live_1234567890abcdef1234567890abcdef
CONEKTA_WEBHOOK_URL=https://tudominio.com/api/webhook/conekta
```

**Importante:** En desarrollo, usa una clave de prueba (`key_test_...`)

### 3. Ejecutar Migraciones

```bash
php artisan migrate
```

Esto crea los campos necesarios en la tabla `ventas`:
- `conekta_session_id`
- `conekta_charge_id`
- `conekta_event_type`
- `conekta_response`

### 4. Configurar Webhooks

1. Ir a https://panel.conekta.io/developers/webhooks
2. Click en "Crear Endpoint"
3. URL: `https://tudominio.com/api/webhook/conekta`
4. Eventos a escuchar:
   - ✅ `charge.paid` - Pago completado
   - ✅ `charge.pending` - Pago pendiente
   - ✅ `charge.expired` - Sesión expirada
   - ✅ `charge.failed` - Pago fallido

### 5. Verificar Configuración

```bash
# En tinker
php artisan tinker

> app(\App\Services\ConektaService::class)
```

Si no hay error, ¡está configurado correctamente!

---

## Flujos de Integración

### Flujo de Pago Estándar

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Cliente selecciona evento y zona                         │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. Ingresa datos: nombre, email, teléfono                   │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. Selecciona método de pago (tarjeta, OXXO, transferencia) │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. Frontend: POST /api/checkout/conekta                     │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. Backend: CheckoutController::createConektaCheckout()     │
│    - Valida datos                                           │
│    - Verifica disponibilidad de boletos                     │
│    - Llama a ConektaService::createCheckoutSession()        │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 6. API Conekta: Crear sesión de checkout                    │
│    Response: {checkout_url, session_id, expires_at}         │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 7. Frontend: Redirigir a https://pay.conekta.io/checkout/...│
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 8. Cliente completa pago en Conekta                         │
│    (Tarjeta, OXXO, Transferencia, etc)                      │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 9. Conekta procesa pago                                     │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 10. Webhook: POST /api/webhook/conekta                      │
│     Event: charge.paid (u otro estado)                      │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 11. Backend: Procesar webhook                               │
│     - Validar datos                                         │
│     - Actualizar estado de orden                            │
│     - Enviar boletos por email                              │
│     - Registrar en BD                                       │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 12. Cliente recibe boletos en email                         │
└─────────────────────────────────────────────────────────────┘
```

---

## Métodos de Pago

### Tarjeta de Crédito/Débito

```json
{
  "payment_method": "card",
  "installments": 1
}
```

**Ventajas:**
- ✅ Pago inmediato
- ✅ Múltiples cuotas
- ✅ Procesadores internacionales

**Tarjetas de prueba:**
- Aprobada: `4242 4242 4242 4242`
- Rechazada: `4000 0000 0000 0002`

### OXXO

```json
{
  "payment_method": "oxxo"
}
```

**Ventajas:**
- ✅ Sin tarjeta
- ✅ En cualquier OXXO
- ✅ Efectivo

**Desventajas:**
- ❌ Pago pendiente 24-72 horas
- ❌ Comisión adicional

### Transferencia Bancaria

```json
{
  "payment_method": "bank_transfer"
}
```

**Ventajas:**
- ✅ Desde cualquier banco
- ✅ Sin comisiones

**Desventajas:**
- ❌ Pago pendiente hasta confirmación
- ❌ Manual en algunos casos

---

## Gestión de Webhooks

### ¿Qué son los Webhooks?

Los webhooks son notificaciones automáticas que Conekta envía a tu servidor cuando ocurren eventos de pago.

### Tipos de Eventos

```
charge.paid
├─ Pago completado exitosamente
├─ Acción: Actualizar orden a 'pagado'
└─ Enviar boletos al cliente

charge.pending
├─ Pago en espera (OXXO, transferencia)
├─ Acción: Actualizar orden a 'pendiente'
└─ Notificar al cliente

charge.expired
├─ Sesión expirada sin pago (24h)
├─ Acción: Actualizar orden a 'expirado'
└─ Permitir crear nueva sesión

charge.failed
├─ Pago rechazado
├─ Acción: Actualizar orden a 'rechazado'
└─ Mostrar error al cliente
```

### Procesamiento Seguro de Webhooks

```php
// En WebhookController::conekta()

public function conekta(Request $request): JsonResponse
{
    try {
        $data = $request->json()->all();
        
        // 1. Validar estructura
        if (!isset($data['type']) || !isset($data['data'])) {
            return response()->json(['error' => 'Estructura inválida'], 400);
        }
        
        // 2. Registrar en BD (para auditoría)
        $webhookLog = WebhookLog::create([
            'provider' => 'conekta',
            'event_type' => $data['type'],
            'payload' => $data,
            'status' => 'pending',
        ]);
        
        // 3. Procesar
        $success = $this->conektaService->processWebhookNotification($data);
        
        // 4. Actualizar log
        $webhookLog->update([
            'status' => $success ? 'success' : 'failed',
        ]);
        
        return response()->json(['ok' => $success], $success ? 200 : 500);
    } catch (\Exception $e) {
        Log::error('Error en webhook', ['error' => $e->getMessage()]);
        return response()->json(['error' => 'Error interno'], 500);
    }
}
```

### Reintentos de Webhooks

Si un webhook falla, puedes retrasarlo:

```php
// Route de admin
POST /api/admin/webhook-logs/{id}/retry
```

---

## Estados de Pago

### Máquina de Estados

```
              ┌─────────────┐
              │  pendiente  │
              └─────────────┘
                    ↑
                    │
         ┌──────────┴──────────┐
         │                     │
    ┌────▼────┐           ┌────▼────┐
    │ pagado  │           │ expirado│
    └─────────┘           └─────────┘
         ▲
         │
    ┌────┴──────┐
    │            │
┌──▼──┐    ┌─────▼──┐
│card │    │bank_tr │
└─────┘    └────────┘
```

### Estados Posibles

```php
'pagado'     // Pago completado, boletos listos
'pendiente'  // Esperando confirmación (OXXO, transferencia)
'procesando' // Procesando pago
'autorizado' // Autorización pendiente de captura
'rechazado'  // Pago fue rechazado
'cancelado'  // Orden cancelada
'expirado'   // Sesión expirada
'reembolsado'// Pago reembolsado
```

### Transiciones Válidas

```
pendiente ──(charge.paid)──> pagado
pendiente ──(charge.failed)──> rechazado
pendiente ──(charge.expired)──> expirado
pagado ──(refund)──> reembolsado
```

---

## Testing

### Ambiente de Desarrollo

```bash
# .env
APP_ENV=local
CONEKTA_API_KEY=key_test_1234567890abcdef
```

Con esta configuración, puedes:
- ✅ Crear sesiones de pago
- ✅ Usar tarjetas de prueba
- ✅ Simular webhooks

### Tarjetas de Prueba

| Escenario | Número | Exp | CVC |
|-----------|--------|-----|-----|
| Aprobada | 4242424242424242 | 12/25 | 424 |
| Rechazada | 4000000000000002 | 12/25 | 424 |
| 3D Secure | 4012888888881881 | 12/25 | 424 |

### Simulación de Webhooks (cURL)

```bash
# Simular pago completado
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
          "email": "test@example.com",
          "name": "Test User",
          "phone": "5551234567"
        },
        "payment_method": {"type": "card"},
        "metadata": {
          "external_reference": "EV1-ZN5-Q2"
        }
      }
    }
  }'
```

---

## Troubleshooting

### Problema: "Conekta no está configurado"

**Causa:** Falta `CONEKTA_API_KEY` en `.env`

```bash
# Solución
echo "CONEKTA_API_KEY=key_test_xxxxx" >> .env
php artisan config:clear
```

### Problema: "No se encontró orden asociada"

**Causa:** El `external_reference` no coincide con la orden

```php
// Verificar que external_reference sea único
$order = Order::where('referencia_pago', 'EV1-ZN5-Q2')->first();
```

### Problema: Webhook no recibido

**Causas posibles:**
1. ❌ URL no es pública (localhost no funciona)
2. ❌ Firewall bloqueando conexión
3. ❌ Webhook no configurado en panel
4. ❌ IP de Conekta bloqueada

**Solución:**
- Verificar que APP_URL sea https en producción
- Usar ngrok en desarrollo: `ngrok http 8000`
- Revisar logs en `/storage/logs/laravel.log`

### Problema: Pago no se refleja en orden

**Pasos de debug:**

```php
// 1. Revisar en tinker
php artisan tinker

// 2. Buscar orden
$order = \App\Models\Order::where('conekta_session_id', 'ses_xxx')->first();
$order // Ver estado

// 3. Revisar logs de webhook
$log = \App\Models\WebhookLog::where('provider', 'conekta')->latest()->first();
$log->payload // Ver datos recibidos
$log->response // Ver respuesta procesada

// 4. Ver error
$log->error
```

### Problema: Error "Credenciales inválidas"

```bash
# Verificar API key
echo $CONEKTA_API_KEY  # Debe ser visible

# Si no está, agregar a .env
CONEKTA_API_KEY=key_live_xxxxx

# Ejecutar
php artisan config:clear
```

---

## Comparación: Mercado Pago vs Conekta

| Aspecto | Mercado Pago | Conekta |
|---------|-------------|---------|
| **Métodos de Pago** | Tarjeta, OXXO, Transferencia | Tarjeta, OXXO, Transferencia, Wallets |
| **Cuotas** | Hasta 12 meses | Desde plataforma |
| **Tiempo de Acreditación** | Inmediato | Inmediato |
| **Comisión** | 2.9% + $0.3 | Configurable por plan |
| **Documentación** | Excelente | Buena |
| **Soporte** | Muy bueno | Bueno |
| **Cobertura** | Latina | México |

---

## ✅ Checklist de Implementación

### Desarrollo
- [ ] Cuenta creada en https://panel.conekta.io
- [ ] Claves de prueba obtenidas
- [ ] CONEKTA_API_KEY agregado a `.env`
- [ ] Migraciones ejecutadas: `php artisan migrate`
- [ ] Servicio ConektaService creado
- [ ] Controller actualizado con createConektaCheckout()
- [ ] Rutas agregadas a `api.php`
- [ ] Webhook configurado en panel (URL: ngrok)
- [ ] Pruebas manuales completadas
- [ ] Documentación leída

### Producción
- [ ] Claves de producción obtenidas
- [ ] CONEKTA_API_KEY actualizado a clave `key_live_`
- [ ] APP_URL es HTTPS
- [ ] Webhook configurado en panel (URL de producción)
- [ ] Logs monitoreados
- [ ] Prueba de transacción real
- [ ] Email de confirmación funciona
- [ ] Boletos se entregan correctamente

---

## 📚 Referencias Útiles

- 📖 [Documentación API Conekta](https://developers.conekta.io)
- 🎛️ [Panel de Control](https://panel.conekta.io)
- 📊 [Dashboard de Pagos](https://panel.conekta.io/dashboard)
- 🔧 [Webhooks](https://panel.conekta.io/developers/webhooks)
- 📞 [Soporte](https://panel.conekta.io/help)

---

## 🚀 Próximos Pasos

1. ✅ Integración básica completada
2. ⏳ Agregar más métodos de pago (Google Pay, Apple Pay)
3. ⏳ Implementar reembolsos
4. ⏳ Dashboard de análisis de pagos
5. ⏳ Integraciones con CRM
6. ⏳ Automatización de reportes

---

**Fecha de Creación:** Mayo 11, 2025
**Última Actualización:** Mayo 11, 2025
**Responsable:** Sistema La Reata Digital
