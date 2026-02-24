# LaReataDigital2

Repositorio base del sistema La Reata Digital, con historial reiniciado y configuración lista para desarrollo.

Sistema web para venta y validación de tickets de charreada con Laravel + MySQL + Vue.

## Stack

- Backend: Laravel 12 + Sanctum
- Frontend: Vue 3 + Vite + Tailwind
- DB: MySQL
- Ticket: Código único por boleto + PDF de descarga

## Configuración rápida

1. Copia variables de entorno:
   - `copy .env.example .env`
2. Ajusta MySQL en `.env`:
   - `DB_CONNECTION=mysql`
   - `DB_HOST=127.0.0.1`
   - `DB_PORT=3306`
   - `DB_DATABASE=lareata_digital`
   - `DB_USERNAME=tu_usuario`
   - `DB_PASSWORD=tu_password`
3. Instala dependencias:
   - `composer install`
   - `npm install`
4. Inicializa app:
   - `php artisan key:generate`
   - `php artisan migrate --seed`

## Desarrollo

- Backend: `php artisan serve`
- Frontend: `npm run dev`

## Usuarios demo

- Admin: `admin@lareata.test` / `password123`
- Validador: `validador@lareata.test` / `password123`
- Comprador: `comprador@lareata.test` / `password123`

## API base

- Público: `GET /api/events`, `GET /api/events/{id}`
- Compra: `POST /api/checkout`
- Auth: `POST /api/auth/register`, `POST /api/auth/login`
- Validador: `POST /api/validator/scan`
- Admin: `/api/admin/events`, `/api/admin/reports/*`
