# Integración de Mercado Pago - Guía Rápida

## ⚡ Setup en 5 Minutos

### 1️⃣ Configurar Credenciales

Agrega a tu `.env`:
```bash
MERCADOPAGO_ACCESS_TOKEN=APP_USR-YOUR_TOKEN_HERE
MERCADOPAGO_PUBLIC_KEY=APP_USR-YOUR_PUBLIC_KEY_HERE
```

Obtén tus credenciales en: https://www.mercadopago.com.mx/developers/es/settings/account/credentials

### 2️⃣ Instalar Dependencias

```bash
composer require mercadopago/sdk-php
```

### 3️⃣ Ejecutar Migración

```bash
php artisan migrate
```

Esto agrega campos a la tabla `ventas` para almacenar datos de transacciones.

### 4️⃣ Configurar Webhook (Opcional pero Recomendado)

1. Ve a https://www.mercadopago.com.mx/developers/es/docs/checkout-pro/landing
2. En tu dashboard, ve a **Configuración** > **Webhooks**
3. Agrega esta URL:
   ```
   https://tudominio.com/api/webhook/mercadopago
   ```

### 5️⃣ Listo para Usar ✅

---

## 🔗 Endpoints Principales

### Crear Preferencia de Pago
```
POST /api/checkout/mercadopago/preference
```

**Body:**
```json
{
  "event_id": 1,
  "event_zone_id": 5,
  "quantity": 2,
  "buyer_name": "Juan Pérez",
  "buyer_email": "juan@example.com",
  "buyer_phone": "5551234567"
}
```

**Response:**
```json
{
  "redirect_url": "https://www.mercadopago.com.mx/checkout/v1/redirect?...",
  "preference_id": "1234567890"
}
```

### Webhook de Notificaciones
```
POST /api/webhook/mercadopago
```

Mercado Pago envía automáticamente notificaciones de cambios de estado.

---

## 📱 Frontend Rápido

### Vue/React - Crear Preferencia

```javascript
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
window.location.href = data.redirect_url;
```

---

## 🧪 Testing

### Tarjetas de Prueba (Sandbox)

| Tipo | Número | Expira | CVV |
|------|--------|--------|-----|
| **Aprobada** | 4111111111111111 | 11/25 | 123 |
| **Rechazada** | 5105105105105100 | 11/25 | 123 |

Otros datos de prueba:
- Email: cualquier email válido
- Documento: cualquier número (ej: 12345678)
- Teléfono: cualquier número

---

## 📊 Campos de BD

Se agregan estos campos a la tabla `ventas`:

| Campo | Descripción |
|-------|-------------|
| `mercadopago_transaction_id` | ID único de transacción |
| `mercadopago_preference_id` | ID de preferencia MP |
| `mercadopago_payment_status` | Estado del pago |
| `mercadopago_response` | JSON completo de respuesta |

---

## 🔐 Seguridad

✅ El SDK ya valida conexiones HTTPS
✅ El Access Token se guarda en `.env` (no en código)
✅ Las notificaciones se validan automáticamente
✅ Todos los pagos se registran en BD

---

## 📚 Documentación Completa

Ver:
- [`MERCADOPAGO_INTEGRATION.md`](./MERCADOPAGO_INTEGRATION.md) - Guía completa
- [`MERCADOPAGO_EJEMPLOS.md`](./MERCADOPAGO_EJEMPLOS.md) - Ejemplos de código

---

## 🚀 Flujo Completo

```
1. Cliente llena formulario de compra
       ↓
2. Frontend llama POST /api/checkout/mercadopago/preference
       ↓
3. Backend retorna redirect_url
       ↓
4. Cliente es redirigido a Mercado Pago
       ↓
5. Cliente ingresa datos de tarjeta
       ↓
6. Mercado Pago procesa el pago
       ↓
7. Si es aprobado:
   - Mercado Pago envía webhook a /api/webhook/mercadopago
   - Backend actualiza estado de orden a "pagado"
   - Se generan y envían boletos por email
       ↓
8. Cliente recibe boletos en su correo ✅
```

---

## ❓ Preguntas Comunes

### ¿Cómo obtengo mis credenciales?
Ve a: https://www.mercadopago.com.mx/developers/es/settings/account/credentials

### ¿Funciona en modo Sandbox?
Sí. Usa las tarjetas de prueba arriba. Se usa automáticamente si tu Access Token es de sandbox.

### ¿Se pueden hacer reembolsos?
Sí, pero necesita código adicional. Contacta al equipo de desarrollo.

### ¿Qué hacer si no llega webhook?
1. Verifica URL en dashboard de Mercado Pago
2. Revisa logs: `storage/logs/laravel.log`
3. Para testing local, usa `ngrok`

### ¿Cómo recibo notificaciones de pago?
El webhook se envía automáticamente. El backend procesa y actualiza la orden.

---

## 🔧 Troubleshooting

| Problema | Solución |
|----------|----------|
| "Mercado Pago no configurado" | Verifica `MERCADOPAGO_ACCESS_TOKEN` en `.env` |
| Webhook no llega | Configura URL en dashboard de Mercado Pago |
| Pago rechazado en sandbox | Usa tarjeta de prueba `5105105105105100` |
| Error CORS | Asegúrate que `/api/*` sea accesible públicamente |

---

## 📞 Soporte

- **Documentación MP:** https://developers.mercadopago.com/es
- **SDK PHP:** https://github.com/mercadopago/sdk-php
- **Dashboard:** https://www.mercadopago.com.mx

---

## ✨ Siguientes Pasos

- [ ] Probar con tarjeta de prueba en sandbox
- [ ] Configurar webhook en Mercado Pago
- [ ] Implementar frontend
- [ ] Pasar a producción

**¡Listo para recibir pagos!** 🎉
