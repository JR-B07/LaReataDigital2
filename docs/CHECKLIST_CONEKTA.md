# ✅ Checklist de Implementación - Conekta

## Fase 1: Preparación (Antes de Empezar)

- [ ] Leer documentación completa de Conekta
- [ ] Crear cuenta en https://panel.conekta.io
- [ ] Verificar identidad (México)
- [ ] Obtener claves de prueba (`key_test_`)
- [ ] Revisar métodos de pago soportados
- [ ] Registrarse en Slack de soporte de Conekta (opcional)

---

## Fase 2: Configuración Local

### Backend
- [ ] Agregar al `.env`:
  ```bash
  CONEKTA_API_KEY=key_test_1234567890abcdef
  CONEKTA_WEBHOOK_URL=http://localhost:8000/api/webhook/conekta
  ```
- [ ] Ejecutar `composer install` (si hay nuevas dependencias)
- [ ] Ejecutar `php artisan migrate`
- [ ] Verificar campos en BD:
  ```bash
  php artisan tinker
  > DB::table('ventas')->first() // Debe mostrar campos conekta_*
  ```

### Servicio
- [ ] ✅ ConektaService creado en `app/Services/`
- [ ] ✅ Métodos implementados:
  - [ ] `createCheckoutSession()`
  - [ ] `getChargeInfo()`
  - [ ] `processWebhookNotification()`
  - [ ] `updateOrderFromWebhookEvent()`

### Controller
- [ ] ✅ CheckoutController actualizado con `createConektaCheckout()`
- [ ] ✅ WebhookController actualizado con:
  - [ ] `conekta()` - Procesar webhooks
  - [ ] `conektaSuccess()` - Redirect success
  - [ ] `conektaFailure()` - Redirect failure

### Rutas
- [ ] ✅ POST `/api/checkout/conekta`
- [ ] ✅ POST `/api/webhook/conekta`
- [ ] ✅ GET `/api/checkout/conekta/success`
- [ ] ✅ GET `/api/checkout/conekta/failure`

### Modelo
- [ ] ✅ Order model actualizado con campos fillable:
  - [ ] `conekta_session_id`
  - [ ] `conekta_charge_id`
  - [ ] `conekta_event_type`
  - [ ] `conekta_response`

### Configuración
- [ ] ✅ `config/services.php` actualizado con sección `conekta`

---

## Fase 3: Testing Local

### Sin ngrok (API calls directas)

- [ ] Crear sesión de checkout:
  ```bash
  php artisan tinker
  > $service = app(\App\Services\ConektaService::class)
  > $result = $service->createCheckoutSession([...])
  > dd($result)
  ```

- [ ] Verificar error si falta API key:
  ```bash
  # Sin CONEKTA_API_KEY en .env
  # Debe retornar: 'Conekta no está configurado'
  ```

### Con ngrok (Webhooks)

- [ ] Iniciar ngrok:
  ```bash
  ngrok http 8000
  ```

- [ ] Copiar URL pública: `https://xxx.ngrok.io`

- [ ] Actualizar en `.env`:
  ```bash
  APP_URL=https://xxx.ngrok.io
  CONEKTA_WEBHOOK_URL=https://xxx.ngrok.io/api/webhook/conekta
  ```

- [ ] Configurar webhook temporal en panel Conekta:
  - URL: `https://xxx.ngrok.io/api/webhook/conekta`
  - Eventos: charge.paid, charge.pending, charge.failed, charge.expired

### Pruebas Manuales

- [ ] Crear orden con tarjeta de prueba (4242...):
  ```bash
  curl -X POST http://localhost:8000/api/checkout/conekta \
    -H "Content-Type: application/json" \
    -d '{...}'
  ```

- [ ] Simular webhook exitoso:
  ```bash
  curl -X POST http://localhost:8000/api/webhook/conekta \
    -H "Content-Type: application/json" \
    -d '{
      "type": "charge.paid",
      "data": {...}
    }'
  ```

- [ ] Verificar orden actualizada en BD:
  ```bash
  php artisan tinker
  > $order = \App\Models\Order::latest()->first()
  > $order->estado_pago // Debe ser 'pagado'
  > $order->conekta_charge_id // Debe tener ID
  ```

---

## Fase 4: Testing Automatizado

- [ ] Tests unitarios:
  ```bash
  php artisan test tests/Unit/ConektaServiceTest.php
  ```

- [ ] Tests de integración:
  ```bash
  php artisan test tests/Feature/ConektaCheckoutTest.php
  ```

- [ ] Tests de webhook:
  ```bash
  php artisan test tests/Feature/ConektaWebhookTest.php
  ```

---

## Fase 5: Frontend

### Vue/React Component

- [ ] Componente de checkout creado:
  ```vue
  <ConektaCheckout :eventId="eventId" :zoneId="zoneId" />
  ```

- [ ] Formulario con campos:
  - [ ] Nombre
  - [ ] Email
  - [ ] Teléfono
  - [ ] Método de pago (select)

- [ ] Botón "Ir a Pagar"

- [ ] Manejo de respuesta:
  - [ ] Redirigir a `checkout_url` en caso de éxito
  - [ ] Mostrar error si falla

### Página de Confirmación

- [ ] Página `/checkout/success`
  - [ ] Mensaje "Pago exitoso"
  - [ ] Mostrar orden ID
  - [ ] Link a descargar boletos

- [ ] Página `/checkout/failure`
  - [ ] Mensaje "Pago fallido"
  - [ ] Botón para reintentar
  - [ ] Link a contactar soporte

---

## Fase 6: Documentación

- [ ] ✅ SETUP_CONEKTA.md - Guía rápida
- [ ] ✅ CONEKTA_INTEGRATION.md - Guía completa
- [ ] ✅ TECNICO_CONEKTA.md - Detalles técnicos
- [ ] ✅ CONEKTA_EJEMPLOS.md - Ejemplos de código
- [ ] ✅ Este checklist

---

## Fase 7: Certificación (Antes de Producción)

### Validaciones Técnicas
- [ ] API key funciona (test + production)
- [ ] Webhook se recibe correctamente
- [ ] Base de datos registra transacciones
- [ ] Emails de confirmación se envían
- [ ] Boletos PDF se generan correctamente
- [ ] Logs no muestran errores

### Validaciones de Seguridad
- [ ] APP_URL usa HTTPS en producción
- [ ] CONEKTA_API_KEY no está en código fuente
- [ ] Credenciales no aparecen en logs
- [ ] Webhooks se procesan de forma segura
- [ ] Validación de estructura de webhook

### Validaciones de Negocio
- [ ] Estados de pago funcionan correctamente
- [ ] Órdenes se marcan como pagadas
- [ ] Órdenes expiradas se manejan
- [ ] Pagos rechazados muestran error al cliente
- [ ] Reportes muestran datos correctos

---

## Fase 8: Deployment a Producción

### Preparación
- [ ] Claves de producción obtenidas de Conekta
- [ ] Backup de BD realizado
- [ ] Planes de rollback documentados

### Deployment
- [ ] Actualizar `.env` en servidor:
  ```bash
  CONEKTA_API_KEY=key_live_production_key_here
  APP_ENV=production
  APP_DEBUG=false
  ```

- [ ] Ejecutar migraciones en producción:
  ```bash
  php artisan migrate --force
  ```

- [ ] Limpiar cache:
  ```bash
  php artisan config:clear
  php artisan cache:clear
  ```

- [ ] Configurar webhook en panel Conekta:
  - URL: `https://tudominio.com/api/webhook/conekta`
  - Eventos: charge.paid, charge.pending, charge.expired, charge.failed

### Validación en Producción
- [ ] Acceder a sitio web
- [ ] Completar flujo de pago exitoso
- [ ] Verificar que orden se crea con estado correcto
- [ ] Verificar que email de confirmación se envía
- [ ] Revisar logs sin errores:
  ```bash
  tail -f storage/logs/laravel.log | grep -i conekta
  ```

### Monitoreo Post-Deployment
- [ ] Monitorear logs diarios
- [ ] Revisar tasa de pago/error
- [ ] Revisar satisfacción de clientes
- [ ] Preparar reportes de transacciones

---

## Fase 9: Análisis y Mejora

### Métricas a Monitorear
- [ ] Tasa de conversión
- [ ] Métodos de pago más usados
- [ ] Tiempo promedio de transacción
- [ ] Tasa de errores/rechazos
- [ ] Ingresos por método de pago

### Optimizaciones Futuras
- [ ] Agregar Google Pay
- [ ] Agregar Apple Pay
- [ ] Implementar reembolsos
- [ ] Dashboard de analítica
- [ ] Integraciones CRM
- [ ] Automatización de reportes

---

## Rollback (Si es necesario)

Si hay problema crítico en producción:

```bash
# 1. Revertir cambios de código
git revert <commit-hash>
git push

# 2. Revertir BD (si es necesario)
php artisan migrate:rollback

# 3. Volver a usar Mercado Pago (si está disponible)
# Actualizar endpoint en frontend de MP a Conekta

# 4. Notificar a clientes
# Email: Usamos Mercado Pago temporalmente
```

---

## FAQ Checklist

**P: ¿Necesito certificado SSL?**
R: Sí, en producción. En desarrollo (ngrok) funciona automáticamente.

**P: ¿Cuánto tiempo toma implementar?**
R: 4-6 horas para desarrollo, 1-2 horas para certificación, 1 hora deployment.

**P: ¿Qué pasa si falla un webhook?**
R: Se registra en `webhook_logs` y se puede reintentar manualmente.

**P: ¿Puedo usar ambos (Mercado Pago y Conekta)?**
R: Sí, están separados por endpoint. Cliente elige en frontend.

**P: ¿Dónde veo los pagos?**
R: Dashboard en https://panel.conekta.io y tabla `ventas` en BD.

---

## Contactos Útiles

- **Soporte Conekta:** https://panel.conekta.io/help
- **Documentación:** https://developers.conekta.io
- **Email Conekta:** support@conekta.io
- **Teléfono (México):** +52 55 9121 0609

---

## Control de Cambios

| Fecha | Responsable | Cambio |
|-------|-------------|--------|
| 2025-05-11 | Sistema | Documento creado |
| | | Fases 1-9 documentadas |

---

**Estado:** ✅ LISTO PARA IMPLEMENTACIÓN
**Nivel de Criticidad:** ALTO (Cambio de proveedor de pagos)
**Estimado de Tiempo:** 12-16 horas
**Riesgo:** BAJO (Integración bien documentada)
