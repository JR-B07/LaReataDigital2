<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket {{ $ticket->ticket_code }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; }
        .card { border: 1px solid #d1d5db; border-radius: 8px; padding: 18px; }
        .title { font-size: 20px; font-weight: bold; margin-bottom: 10px; }
        .row { margin-bottom: 6px; }
        .label { font-weight: bold; }
        .code { margin-top: 16px; font-size: 14px; background: #f3f4f6; padding: 10px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">{{ $ticket->event->name }}</div>
        <div class="row"><span class="label">Fecha:</span> {{ $ticket->event->starts_at }}</div>
        <div class="row"><span class="label">Lugar:</span> {{ $ticket->event->venue }} ({{ $ticket->event->city }})</div>
        <div class="row"><span class="label">Zona:</span> {{ $ticket->zone->name }}</div>
        <div class="row"><span class="label">Comprador:</span> {{ $ticket->order->buyer_name }}</div>
        <div class="row"><span class="label">Precio:</span> ${{ number_format($ticket->item->unit_price, 2) }}</div>
        <div class="code">Código: {{ $ticket->ticket_code }}</div>
    </div>
</body>
</html>
