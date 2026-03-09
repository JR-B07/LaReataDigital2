# Actualización sugerida de contrato de desarrollo

## 1) Objeto del contrato
El presente contrato tiene por objeto el desarrollo, implementación y entrega del sistema web ChárroTickets para gestión de venta, emisión y validación de boletos digitales para eventos de charreada, con panel administrativo y módulo de reportes.

## 2) Alcance funcional entregable
El proveedor entrega las siguientes funcionalidades:

1. **Módulo público de eventos**
   - Listado de eventos públicos.
   - Consulta de detalle de evento por identificador.

2. **Módulo de compra (checkout)**
   - Compra de boletos por evento y zona.
   - Captura de datos de comprador (nombre, correo, teléfono opcional).
   - Selección de método de pago soportado por el sistema: tarjeta, OXXO o transferencia.
   - Aplicación opcional de código de descuento (monto fijo o porcentaje), sujeto a vigencia, uso máximo y activación.

3. **Generación y consulta de boletos**
   - Emisión de boletos con código único por ticket.
   - Consulta de ticket por código.
   - Descarga de ticket en formato PDF.

4. **Módulo de validación en acceso**
   - Escaneo/validación de ticket por código.
   - Resultados de validación: válido, usado o inválido.
   - Registro de bitácora de escaneos (resultado, mensaje, fecha y validador).

5. **Módulo administrativo**
   - Alta, edición, consulta y baja de eventos.
   - Configuración de zonas por evento (capacidad y precio).
   - Publicación y cancelación de eventos.
   - Asignación y desasignación de validadores por evento.

6. **Módulo de reportes**
   - Resumen general: órdenes, boletos vendidos, ingresos, asistencia y intentos de fraude.
   - Reporte de ventas por zona.

## 3) Roles y permisos
El sistema opera con los siguientes roles:

- **Admin**: gestión completa de eventos, validadores y reportes.
- **Validator**: validación de boletos y consulta de escaneos según permisos.
- **Buyer**: compra de boletos y consulta de historial de órdenes del usuario autenticado.

## 4) Reglas de negocio incluidas

- Un evento puede estar en estado: borrador, publicado o cancelado.
- Solo se permiten compras en eventos publicados.
- La compra está sujeta a disponibilidad por zona.
- Límite de compra por operación: mínimo 1 y máximo 20 boletos.
- Cada boleto se genera con código único irrepetible.
- Un boleto activo cambia a estado usado al primer escaneo válido.
- Los códigos de descuento aplican solo si están activos, vigentes y con cupo de uso disponible.

## 5) Entregables técnicos

- Backend en Laravel 12.
- API autenticada con Sanctum.
- Frontend con Vue 3, Vite y Tailwind.
- Base de datos MySQL con migraciones y seeder de usuarios demo.
- Generación de PDF para boletos descargables.

## 6) Criterios de aceptación
Se considerará aceptada la entrega cuando se validen, al menos, los siguientes escenarios:

1. Alta de evento con zonas y publicación exitosa.
2. Compra exitosa de boletos con decremento de disponibilidad.
3. Emisión de ticket con código único y descarga en PDF.
4. Escaneo válido de ticket activo.
5. Segundo escaneo del mismo ticket marcado como usado.
6. Escaneo de código inexistente marcado como inválido.
7. Visualización de reportes de resumen y ventas por zona.

## 7) Exclusiones del alcance actual
Quedan fuera del alcance, salvo convenio adicional por escrito:

- Integración operativa con pasarela bancaria de cobro y conciliación real.
- Facturación electrónica CFDI.
- App móvil nativa iOS/Android.
- Integraciones con ERPs o CRMs de terceros.
- Tableros BI externos (Power BI, Looker, etc.).

## 8) Supuestos y dependencias

- El cliente proveerá hosting, dominio y accesos necesarios.
- El cliente define textos legales, aviso de privacidad y políticas de reembolso.
- El cliente proveerá branding final (logo, paleta, tipografía) para personalización definitiva.

## 9) Garantía y soporte

- Garantía correctiva por defectos de software: **30 días naturales** posteriores a la aceptación.
- Soporte evolutivo y nuevas funcionalidades: mediante bolsa de horas o contrato adicional.

## 10) Control de cambios
Todo cambio de alcance posterior a la firma deberá documentarse mediante solicitud de cambio, incluyendo impacto en costo y plazo, y requerirá aprobación escrita de ambas partes.

---

## Anexo A (opcional): Matriz rápida alcance vs no alcance

### Incluido
- Venta de boletos por evento y zona.
- Códigos de descuento.
- Ticket digital descargable en PDF.
- Validación y bitácora de escaneos.
- Panel admin y reportes.

### No incluido
- Pasarela de pago productiva integrada.
- CFDI.
- App móvil nativa.
- Integraciones empresariales externas.
