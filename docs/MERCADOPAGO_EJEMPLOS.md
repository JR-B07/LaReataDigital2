# Ejemplos de Implementación - Mercado Pago

## JavaScript/Vue - Flujo de Checkout

### 1. Componente Vue para Seleccionar Pago

```vue
<template>
  <div class="checkout-container">
    <h2>Selecciona tu Método de Pago</h2>
    
    <!-- Resumen de Compra -->
    <div class="order-summary">
      <p>Evento: {{ event.name }}</p>
      <p>Zona: {{ zone.nombre }}</p>
      <p>Cantidad: {{ quantity }} boletos</p>
      <p class="total">Total: ${{ total.toFixed(2) }} MXN</p>
    </div>

    <!-- Datos del Comprador -->
    <form @submit.prevent="submitCheckout">
      <div class="form-group">
        <label>Nombre Completo *</label>
        <input 
          v-model="form.buyer_name" 
          type="text" 
          required 
          maxlength="255"
        >
      </div>

      <div class="form-group">
        <label>Correo Electrónico *</label>
        <input 
          v-model="form.buyer_email" 
          type="email" 
          required 
          maxlength="255"
        >
      </div>

      <div class="form-group">
        <label>Teléfono (opcional)</label>
        <input 
          v-model="form.buyer_phone" 
          type="tel" 
          maxlength="30"
          placeholder="5551234567"
        >
      </div>

      <!-- Opción: Tarjeta de Crédito/Débito -->
      <div class="payment-option">
        <h3>💳 Tarjeta de Crédito/Débito</h3>
        <p>Seguro y rápido. Powered by Mercado Pago</p>
        <button 
          type="button" 
          @click="payWithMercadoPago"
          :disabled="loading"
          class="btn btn-primary"
        >
          {{ loading ? 'Procesando...' : 'Pagar con Mercado Pago' }}
        </button>
      </div>

      <!-- Opción: OXXO -->
      <div class="payment-option">
        <h3>🏪 OXXO</h3>
        <p>Paga en cualquier tienda OXXO</p>
        <button 
          type="submit" 
          name="payment_method" 
          value="oxxo"
          :disabled="loading"
          class="btn btn-secondary"
        >
          Pagar en OXXO
        </button>
      </div>

      <!-- Opción: Transferencia -->
      <div class="payment-option">
        <h3>🏦 Transferencia Bancaria</h3>
        <p>Transferencia a nuestra cuenta bancaria</p>
        <button 
          type="submit" 
          name="payment_method" 
          value="transfer"
          :disabled="loading"
          class="btn btn-secondary"
        >
          Pagar por Transferencia
        </button>
      </div>
    </form>

    <!-- Mensaje de Error -->
    <div v-if="error" class="alert alert-danger">
      {{ error }}
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  event: Object,
  zone: Object,
  quantity: Number,
  total: Number,
});

const form = ref({
  buyer_name: '',
  buyer_email: '',
  buyer_phone: '',
  event_id: props.event.id,
  event_zone_id: props.zone.id,
  quantity: props.quantity,
});

const loading = ref(false);
const error = ref('');

const payWithMercadoPago = async () => {
  loading.value = true;
  error.value = '';

  try {
    const response = await fetch('/api/checkout/mercadopago/preference', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(form.value),
    });

    if (!response.ok) {
      const data = await response.json();
      throw new Error(data.message || 'Error al crear preferencia de pago');
    }

    const data = await response.json();
    
    // Redirigir a Mercado Pago
    window.location.href = data.redirect_url;
  } catch (err) {
    error.value = err.message;
    console.error('Error:', err);
  } finally {
    loading.value = false;
  }
};

const submitCheckout = async (e) => {
  const paymentMethod = e.submitter.value;
  
  loading.value = true;
  error.value = '';

  try {
    const response = await fetch('/api/checkout', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        ...form.value,
        payment_method: paymentMethod,
      }),
    });

    if (!response.ok) {
      const data = await response.json();
      throw new Error(data.message || 'Error en el pago');
    }

    const data = await response.json();
    
    // Redirigir a página de confirmación
    window.location.href = `/confirmacion?order=${data.order.id}`;
  } catch (err) {
    error.value = err.message;
    console.error('Error:', err);
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.checkout-container {
  max-width: 500px;
  margin: 0 auto;
  padding: 20px;
}

.order-summary {
  background: #f5f5f5;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
}

.order-summary .total {
  font-size: 20px;
  font-weight: bold;
  color: #1f1f1f;
  margin-top: 10px;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
}

.form-group input {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.payment-option {
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  padding: 15px;
  margin: 15px 0;
}

.payment-option h3 {
  margin: 0 0 5px 0;
  font-size: 16px;
}

.payment-option p {
  color: #999;
  font-size: 13px;
  margin: 5px 0 10px 0;
}

.btn {
  width: 100%;
  padding: 12px;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-primary {
  background: #3483fa;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #2a68d9;
}

.btn-secondary {
  background: #ffffff;
  color: #333;
  border: 1px solid #ddd;
}

.btn-secondary:hover:not(:disabled) {
  background: #f5f5f5;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.alert {
  padding: 12px;
  border-radius: 4px;
  margin-top: 15px;
}

.alert-danger {
  background: #fee;
  color: #c33;
  border: 1px solid #fcc;
}
</style>
```

---

## HTML Puro - Formulario Simple

```html
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pagar Boletos - La Reata Digital</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto; }
    .container { max-width: 500px; margin: 50px auto; padding: 20px; }
    h1 { text-align: center; margin-bottom: 30px; }
    .summary { background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    .form-group { margin-bottom: 15px; }
    label { display: block; margin-bottom: 5px; font-weight: 600; }
    input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
    button { width: 100%; padding: 12px; margin-top: 10px; background: #3483fa; color: white; border: none; border-radius: 4px; font-size: 16px; font-weight: 600; cursor: pointer; }
    button:hover { background: #2a68d9; }
    .error { color: #c33; margin-top: 10px; }
  </style>
</head>
<body>
  <div class="container">
    <h1>🎫 Comprar Boletos</h1>

    <div class="summary">
      <p><strong>Evento:</strong> Concierto La Reata</p>
      <p><strong>Zona:</strong> VIP</p>
      <p><strong>Cantidad:</strong> 2 boletos</p>
      <p><strong style="font-size: 20px; color: #1f1f1f;">Total: $500.00 MXN</strong></p>
    </div>

    <form id="checkoutForm">
      <div class="form-group">
        <label for="name">Nombre Completo</label>
        <input type="text" id="name" name="buyer_name" required>
      </div>

      <div class="form-group">
        <label for="email">Correo Electrónico</label>
        <input type="email" id="email" name="buyer_email" required>
      </div>

      <div class="form-group">
        <label for="phone">Teléfono (opcional)</label>
        <input type="tel" id="phone" name="buyer_phone">
      </div>

      <input type="hidden" name="event_id" value="1">
      <input type="hidden" name="event_zone_id" value="5">
      <input type="hidden" name="quantity" value="2">

      <button type="button" onclick="payWithMercadoPago()">
        💳 Pagar con Tarjeta
      </button>

      <div id="error" class="error"></div>
    </form>
  </div>

  <script>
    async function payWithMercadoPago() {
      const form = document.getElementById('checkoutForm');
      const errorDiv = document.getElementById('error');
      
      const formData = new FormData(form);
      const data = Object.fromEntries(formData);

      try {
        const response = await fetch('/api/checkout/mercadopago/preference', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        });

        if (!response.ok) {
          const error = await response.json();
          throw new Error(error.message);
        }

        const result = await response.json();
        window.location.href = result.redirect_url;
      } catch (error) {
        errorDiv.textContent = '❌ Error: ' + error.message;
      }
    }
  </script>
</body>
</html>
```

---

## React - Hook Personalizado

```jsx
// hooks/useMercadoPago.js
import { useState } from 'react';

export function useMercadoPago() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const createPreference = async (checkoutData) => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch('/api/checkout/mercadopago/preference', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(checkoutData),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Error creating preference');
      }

      const data = await response.json();
      return data;
    } catch (err) {
      setError(err.message);
      throw err;
    } finally {
      setLoading(false);
    }
  };

  const redirectToMercadoPago = async (checkoutData) => {
    try {
      const preference = await createPreference(checkoutData);
      window.location.href = preference.redirect_url;
    } catch (err) {
      console.error('Failed to redirect to Mercado Pago:', err);
    }
  };

  return {
    loading,
    error,
    createPreference,
    redirectToMercadoPago,
  };
}
```

```jsx
// components/CheckoutButton.jsx
import { useMercadoPago } from '../hooks/useMercadoPago';

export function CheckoutButton({ eventId, zoneId, quantity, formData }) {
  const { loading, error, redirectToMercadoPago } = useMercadoPago();

  const handleClick = async () => {
    await redirectToMercadoPago({
      event_id: eventId,
      event_zone_id: zoneId,
      quantity: quantity,
      ...formData,
    });
  };

  return (
    <>
      <button 
        onClick={handleClick} 
        disabled={loading}
        className="checkout-btn"
      >
        {loading ? 'Procesando...' : '💳 Pagar con Tarjeta'}
      </button>
      {error && <p className="error">{error}</p>}
    </>
  );
}
```

---

## cURL - Pruebas en Terminal

```bash
# 1. Crear preferencia de pago
curl -X POST http://localhost:8000/api/checkout/mercadopago/preference \
  -H "Content-Type: application/json" \
  -d '{
    "event_id": 1,
    "event_zone_id": 5,
    "quantity": 2,
    "buyer_name": "Juan Pérez",
    "buyer_email": "juan@example.com",
    "buyer_phone": "5551234567"
  }'

# Respuesta exitosa:
# {
#   "redirect_url": "https://www.mercadopago.com.mx/checkout/v1/redirect?...",
#   "preference_id": "1234567890"
# }
```

---

## PHP - Uso del Servicio

```php
<?php

namespace App\Http\Controllers\Api;

use App\Services\MercadoPagoService;
use Illuminate\Http\Request;

class CustomCheckoutController
{
    public function __construct(private MercadoPagoService $mercadoPagoService) {}

    public function processPay(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:10'],
            'description' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        $preference = $this->mercadoPagoService->createPreference([
            'title' => $data['description'],
            'quantity' => 1,
            'unit_price' => $data['amount'],
            'payer_name' => 'Cliente',
            'payer_email' => $data['email'],
            'payer_phone' => '',
            'external_reference' => 'custom-' . time(),
        ]);

        if (!$preference['success']) {
            return response()->json($preference, 422);
        }

        return response()->json($preference);
    }
}
```

---

## Testing con Insomnia/Postman

### Importar Colección

```json
{
  "info": {
    "name": "Mercado Pago API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Crear Preferencia",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"event_id\": 1,\n  \"event_zone_id\": 5,\n  \"quantity\": 2,\n  \"buyer_name\": \"Juan Pérez\",\n  \"buyer_email\": \"juan@example.com\",\n  \"buyer_phone\": \"5551234567\"\n}"
        },
        "url": {
          "raw": "http://localhost:8000/api/checkout/mercadopago/preference",
          "protocol": "http",
          "host": ["localhost"],
          "port": "8000",
          "path": ["api", "checkout", "mercadopago", "preference"]
        }
      }
    }
  ]
}
```

---

## Manejando Respuestas

### Éxito
```javascript
{
  "redirect_url": "https://www.mercadopago.com.mx/checkout/v1/redirect?pref_id=1234567890",
  "preference_id": "1234567890"
}

// Redirigir al usuario:
window.location.href = redirect_url;
```

### Error
```javascript
{
  "message": "No hay suficientes boletos disponibles en esta zona.",
  "details": null
}

// Mostrar error al usuario
alert(response.message);
```

---

## Validación del Frontend

```javascript
function validateCheckoutForm(data) {
  if (!data.buyer_name || data.buyer_name.trim() === '') {
    return { valid: false, error: 'El nombre es requerido' };
  }

  if (!data.buyer_email || !isValidEmail(data.buyer_email)) {
    return { valid: false, error: 'Email inválido' };
  }

  if (data.quantity < 1 || data.quantity > 20) {
    return { valid: false, error: 'Cantidad inválida (1-20)' };
  }

  return { valid: true };
}

function isValidEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}
```

---

## Próximas Mejoras

- [ ] Agregar SDK de Mercado Pago.js para más opciones de pago
- [ ] Mostrar tarjetas guardadas del usuario
- [ ] Pagos cuotas/plazos
- [ ] Descuentos y códigos promocionales
- [ ] Historial de transacciones en dashboard
