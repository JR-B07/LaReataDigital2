# Integración de Conekta - Guía Rápida

## ⚡ Setup en 5 Minutos

### 1️⃣ Configurar Credenciales

Agrega a tu `.env`:
```bash
CONEKTA_API_KEY=key_live_XXXXXXXXXX_OR_key_test_XXXXXXXXXX
CONEKTA_WEBHOOK_URL=https://tudominio.com/api/webhook/conekta
```

Obtén tus credenciales en: https://panel.conekta.io/developers/dashboard

### 2️⃣ Ejecutar Migración

```bash
php artisan migrate
```

Esto agrega campos a la tabla `ventas` para almacenar datos de transacciones de Conekta.

### 3️⃣ Configurar Webhook

1. Ve a https://panel.conekta.io/developers/webhooks
2. Haz clic en "Agregar evento"
3. Agrega esta URL:
   ```
   https://tudominio.com/api/webhook/conekta
   ```
4. Selecciona estos eventos:
   - `charge.paid` - Pago completado
   - `charge.pending` - Pago pendiente
   - `charge.expired` - Sesión expirada
   - `charge.failed` - Pago fallido

### 4️⃣ Listo para Usar ✅

---

## 🔗 Endpoints Principales

### Crear Sesión de Checkout
```
POST /api/checkout/conekta
```

**Body:**
```json
{
  "event_id": 1,
  "event_zone_id": 5,
  "quantity": 2,
  "buyer_name": "Juan Pérez",
  "buyer_email": "juan@example.com",
  "buyer_phone": "5551234567",
  "payment_method": "card",
  "success_url": "https://tudominio.com/compra?success=true",
  "failure_url": "https://tudominio.com/compra?success=false"
}
```

**Response:**
```json
{
  "checkout_url": "https://pay.conekta.io/checkout/...",
  "session_id": "ses_2nCcd2EG0WVE3w",
  "expires_at": 1715000000,
  "back_urls": {
    "success": "https://tudominio.com/compra?success=true",
    "failure": "https://tudominio.com/compra?success=false"
  }
}
```

### Webhook de Notificaciones
```
POST /api/webhook/conekta
```

Conekta envía automáticamente notificaciones de cambios de estado.

### Endpoints de Retorno
```
GET /api/checkout/conekta/success
GET /api/checkout/conekta/failure
```

---

## 📱 Frontend - Crear Sesión de Checkout

### JavaScript/Vue
```javascript
const createConektaCheckout = async () => {
  const response = await fetch('/api/checkout/conekta', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      event_id: 1,
      event_zone_id: 5,
      quantity: 2,
      buyer_name: 'Juan Pérez',
      buyer_email: 'juan@example.com',
      buyer_phone: '5551234567',
      payment_method: 'card'
    })
  });

  const data = await response.json();
  // Redirigir al checkout de Conekta
  window.location.href = data.checkout_url;
};
```

---

## 🧪 Testing

### Modo Sandbox
En `.env`, usa una clave de prueba:
```bash
CONEKTA_API_KEY=key_test_xxxxxxxxxxxxxxxxxxxxxx
```

### Tarjetas de Prueba

| Tipo | Número | Expira | CVV |
|------|--------|--------|-----|
| **Aprobada** | 4242424242424242 | 12/25 | 424 |
| **Rechazada** | 4000000000000002 | 12/25 | 424 |

Otros datos de prueba:
- Email: cualquier email válido
- Nombre: cualquier nombre
- Teléfono: 5551234567

---

## 📊 Campos de BD

Se agregan estos campos a la tabla `ventas`:

| Campo | Descripción |
|-------|-------------|
| `conekta_session_id` | ID único de sesión de checkout |
| `conekta_charge_id` | ID de cargo/transacción |
| `conekta_event_type` | Tipo de evento (charge.paid, charge.pending, etc) |
| `conekta_response` | JSON completo de respuesta de webhook |

---

## 🔐 Seguridad

✅ Las credenciales se guardan en `.env` (no en código)
✅ Las notificaciones se validan automáticamente
✅ Todos los pagos se registran en BD
✅ Las sesiones expiran automáticamente en 24 horas

---

## 📚 Documentación Completa

Ver:
- [`CONEKTA_INTEGRATION.md`](./CONEKTA_INTEGRATION.md) - Guía completa
- [`CONEKTA_EJEMPLOS.md`](./CONEKTA_EJEMPLOS.md) - Ejemplos de código
- [`TECNICO_CONEKTA.md`](./TECNICO_CONEKTA.md) - Detalles técnicos

---

## 🚀 Métodos de Pago Soportados

✅ Tarjeta de crédito/débito
✅ OXXO
✅ Transferencia bancaria
✅ Google Pay
✅ Apple Pay
✅ PayPal

---

## 🔗 Links Útiles

- 📖 [Documentación API de Conekta](https://developers.conekta.io)
- 🎛️ [Panel de Administración](https://panel.conekta.io)
- 💬 [Soporte Conekta](https://panel.conekta.io/help)

---

## ✅ Checklist de Implementación

- [ ] Crear cuenta en https://panel.conekta.io
- [ ] Obtener claves de API (test y producción)
- [ ] Agregar CONEKTA_API_KEY a `.env`
- [ ] Ejecutar migraciones: `php artisan migrate`
- [ ] Configurar webhook en panel de Conekta
- [ ] Probar con tarjetas de prueba
- [ ] Cambiar a clave de producción
- [ ] Monitorear logs en `/storage/logs`
