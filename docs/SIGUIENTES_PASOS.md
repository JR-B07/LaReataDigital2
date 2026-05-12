# ✅ Guía Final - Conekta Configurado

**Fecha:** Mayo 11, 2025  
**Estado:** 🟢 Código actualizado con tus eventos de webhook

---

## ✅ Lo Que Se Ha Hecho

### Cambios en el Código

El servicio `ConektaService.php` fue actualizado para procesar exactamente los eventos que configuraste en Conekta:

✅ **charge.paid** → Orden se marca como "pagado"  
✅ **charge.pending_confirmation** → Orden se marca como "pendiente"  
✅ **charge.declined** → Orden se marca como "rechazado"  
✅ **charge.expired** → Orden se marca como "expirado"  

**Archivos modificados:**
- `app/Services/ConektaService.php` (líneas 180 y 254)

### Cambios en tu Panel Conekta

Ya realizaste en el panel:
- ✅ Creaste cuenta
- ✅ Obtuviste API Key
- ✅ Configuraste webhook con estos eventos
- ✅ Agregaste a `.env`: `CONEKTA_API_KEY=...`

---

## 📋 Próximos Pasos (Que Aún Falta)

### 1️⃣ Ejecutar Migraciones (2 minutos)

```bash
php artisan migrate
```

Esto crea los campos en la tabla `ventas` para Conekta.

### 2️⃣ Limpiar Cache (1 minuto)

```bash
php artisan config:clear
php artisan cache:clear
```

### 3️⃣ Testing Local (30 minutos)

#### Opción A: Sin ngrok (Solo API calls)
```bash
# Probar creación de sesión
php artisan tinker

> $service = app(\App\Services\ConektaService::class)
> $result = $service->createCheckoutSession([
    'title' => 'Test',
    'quantity' => 1,
    'unit_price' => 500,
    'payer_name' => 'Test',
    'payer_email' => 'test@example.com',
    'payer_phone' => '5551234567',
    'external_reference' => 'TEST123',
    'success_url' => 'http://localhost:8000/success',
    'failure_url' => 'http://localhost:8000/failure'
  ])
> dd($result)
```

#### Opción B: Con ngrok (Webhooks reales)
```bash
# Terminal 1: Iniciar ngrok
ngrok http 8000

# Te mostrará: https://xxx.ngrok.io

# Terminal 2: Actualizar .env
CONEKTA_WEBHOOK_URL=https://xxx.ngrok.io/api/webhook/conekta

# Terminal 2: Reiniciar servidor
php artisan serve

# Luego ir al panel Conekta y cambiar webhook URL a ngrok
```

### 4️⃣ Prueba Completa (1 hora)

1. **Crear orden:**
   ```bash
   curl -X POST http://localhost:8000/api/checkout/conekta \
     -H "Content-Type: application/json" \
     -d '{
       "event_id": 1,
       "event_zone_id": 1,
       "quantity": 1,
       "buyer_name": "Juan Pérez",
       "buyer_email": "juan@example.com",
       "buyer_phone": "5551234567",
       "payment_method": "card"
     }'
   ```

2. **Respuesta esperada:**
   ```json
   {
     "checkout_url": "https://pay.conekta.io/checkout/...",
     "session_id": "ses_2nCcd2EG0WVE3w",
     "expires_at": 1715000000
   }
   ```

3. **Simular webhook exitoso:**
   ```bash
   curl -X POST http://localhost:8000/api/webhook/conekta \
     -H "Content-Type: application/json" \
     -d '{
       "type": "charge.paid",
       "data": {
         "object": {
           "id": "ch_test_12345",
           "status": "paid",
           "amount": 50000,
           "currency": "MXN",
           "customer_info": {
             "email": "juan@example.com",
             "name": "Juan Pérez",
             "phone": "5551234567"
           },
           "payment_method": {"type": "card"},
           "metadata": {
             "external_reference": "TEST123"
           }
         }
       }
     }'
   ```

4. **Verificar en BD:**
   ```bash
   php artisan tinker
   > $order = \App\Models\Order::latest()->first()
   > $order->estado_pago      // Debe ser 'pagado'
   > $order->conekta_charge_id // Debe tener ID
   ```

### 5️⃣ Deploy a Producción (cuando estés listo)

1. **Obtener clave de producción:**
   - Ir a https://panel.conekta.io
   - Copiar clave `key_live_...`

2. **Actualizar .env en servidor:**
   ```bash
   CONEKTA_API_KEY=key_live_xxxxxxxxxxxx
   APP_ENV=production
   ```

3. **Ejecutar migraciones:**
   ```bash
   php artisan migrate --force
   ```

4. **Configurar webhook en panel:**
   - URL: `https://tudominio.com/api/webhook/conekta`
   - Eventos: charge.paid, charge.pending_confirmation, charge.declined, charge.expired

5. **Prueba real:**
   - Hacer una compra real con tarjeta de prueba o real
   - Verificar que la orden se actualiza correctamente

---

## 📊 Estados de Pago

Tu sistema ahora maneja estos estados:

```
pendiente (inicial)
  ↓
├─ pagado (charge.paid)
├─ pendiente (charge.pending_confirmation)
├─ rechazado (charge.declined)
└─ expirado (charge.expired)
```

---

## 🧪 Tarjetas de Prueba

En modo sandbox (`key_test_...`):

| Tipo | Número | Exp | CVC |
|------|--------|-----|-----|
| Aprobada | 4242 4242 4242 4242 | 12/25 | 424 |
| Rechazada | 4000 0000 0000 0002 | 12/25 | 424 |

---

## 📁 Archivos Relevantes

**Backend:**
- `app/Services/ConektaService.php` - Lógica de Conekta
- `app/Http/Controllers/Api/CheckoutController.php` - Endpoint de checkout
- `app/Http/Controllers/Api/WebhookController.php` - Manejo de webhooks
- `routes/api.php` - Rutas API

**BD:**
- `database/migrations/2026_05_03_000002_add_conekta_fields_to_ventas_table.php` - Campos nuevos

**Config:**
- `config/services.php` - Credenciales
- `.env` - Variables de entorno

**Documentación:**
- `docs/SETUP_CONEKTA.md` - Setup rápido
- `docs/CONEKTA_INTEGRATION.md` - Guía completa
- `docs/TECNICO_CONEKTA.md` - Detalles técnicos
- `docs/CONEKTA_EJEMPLOS.md` - Ejemplos de código
- `docs/CHECKLIST_CONEKTA.md` - Checklist de implementación

---

## 🔗 Links Útiles

- 🎛️ [Panel Conekta](https://panel.conekta.io)
- 🔧 [Webhooks](https://panel.conekta.io/developers/webhooks)
- 📖 [Documentación API](https://developers.conekta.io)
- 💬 [Soporte](https://panel.conekta.io/help)

---

## ✨ Resumen

**Lo que está listo:**
- ✅ Código de Conekta 100% implementado
- ✅ Eventos configurados correctamente
- ✅ Webhooks listos
- ✅ Documentación completa

**Lo que debes hacer:**
1. Ejecutar migraciones: `php artisan migrate`
2. Probar en desarrollo (30 min)
3. Deploy a producción (cuando estés listo)

**Estimado de tiempo total:** 2-3 horas

---

## 🆘 Si Algo Falla

**Revisa los logs:**
```bash
tail -f storage/logs/laravel.log | grep -i conekta
```

**Problemas comunes:**

| Problema | Causa | Solución |
|----------|-------|----------|
| "No configurado" | Falta CONEKTA_API_KEY | Agregar a .env y ejecutar `php artisan config:clear` |
| Webhook no llega | URL no pública | Usar ngrok: `ngrok http 8000` |
| Orden no se actualiza | Webhook no configurado | Ir a panel.conekta.io/developers/webhooks |
| Error 422 | Validación fallida | Revisar `storage/logs/laravel.log` |

---

## 🎯 Siguiente

¿Necesitas ayuda con algo específico o ya estás listo para empezar con las pruebas?

**Opciones:**
- ▶️ Empezar a probar ahora
- 📖 Leer más documentación
- ❓ Hacer una pregunta específica
