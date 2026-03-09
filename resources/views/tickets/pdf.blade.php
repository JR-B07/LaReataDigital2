<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Ticket {{ $ticket->ticket_code }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 12px;
            margin: 24px;
        }

        .card {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 16px;
        }

        .header {
            margin-bottom: 14px;
        }

        .title {
            font-size: 20px;
            font-weight: bold;
            margin: 0 0 4px 0;
        }

        .subtitle {
            font-size: 12px;
            color: #6b7280;
            margin: 0;
        }

        .status {
            float: right;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 3px 10px;
            font-size: 11px;
        }

        .section {
            margin-top: 12px;
        }

        .row {
            margin-bottom: 5px;
        }

        .label {
            font-weight: bold;
        }

        .code {
            margin-top: 12px;
            font-size: 14px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
        }

        .terms {
            margin-top: 14px;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
            font-size: 11px;
            color: #374151;
            line-height: 1.4;
        }
    </style>
</head>

<body>
    @php
    $startsAt = optional($ticket->event->starts_at)->timezone(config('app.timezone'));
    $formattedDate = $startsAt ? $startsAt->format('d/m/Y H:i') : 'Por definir';
    $statusText = $ticket->status === 'used' ? 'USADO' : 'ACTIVO';
    $price = $ticket->item?->unit_price ?? 0;
    @endphp

    <div class="card">
        <div class="status">{{ $statusText }}</div>
        <div class="header">
            <h1 class="title">{{ $ticket->event->name }}</h1>
            <p class="subtitle">Ticket digital de acceso</p>
        </div>

        <div class="section">
            <div class="row"><span class="label">Fecha y hora:</span> {{ $formattedDate }}</div>
            <div class="row"><span class="label">Lugar:</span> {{ $ticket->event->venue }} ({{ $ticket->event->city }})</div>
            <div class="row"><span class="label">Zona:</span> {{ $ticket->zone->name }}</div>
            <div class="row"><span class="label">Asiento:</span> {{ $ticket->seat ?: 'General' }}</div>
        </div>

        <div class="section">
            <div class="row"><span class="label">Comprador:</span> {{ $ticket->order->buyer_name }}</div>
            <div class="row"><span class="label">Correo:</span> {{ $ticket->order->buyer_email }}</div>
            <div class="row"><span class="label">Precio:</span> ${{ number_format($price, 2) }}</div>
        </div>

        <div class="code">Código: {{ $ticket->ticket_code }}</div>

        <div class="terms">
            Este boleto es válido para un solo acceso y se considera usado al primer escaneo exitoso.<br>
            La organización podrá solicitar identificación del titular en caso de incidencias de validación.
        </div>
    </div>
</body>

</html>
