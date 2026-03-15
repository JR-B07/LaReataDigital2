<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Boletos de {{ $event->name }}</title>
</head>

<body style="margin:0;padding:0;background:#f7f1ea;color:#2b1607;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f7f1ea;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="680" cellspacing="0" cellpadding="0" style="width:680px;max-width:100%;background:#ffffff;border:1px solid #ead8c0;">
                    <tr>
                        <td style="padding:28px 32px;background:#2b0d00;color:#f0c060;">
                            <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;opacity:0.85;">Marca MGR</div>
                            <div style="font-size:30px;font-weight:bold;margin-top:8px;">Tus boletos estan listos</div>
                            <div style="font-size:14px;color:#f8e9cf;margin-top:10px;">Adjuntamos tus PDFs para el acceso al evento.</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px;">
                            <p style="margin:0 0 16px 0;font-size:16px;">Hola {{ $order->buyer_name ?: 'cliente' }},</p>
                            <p style="margin:0 0 18px 0;font-size:14px;line-height:1.6;">Tu compra fue confirmada correctamente. En este correo van adjuntos tus boletos en PDF y aqui mismo tienes el resumen para consultarlos rapido.</p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#fff8f0;border:1px solid #ead8c0;">
                                <tr>
                                    <td style="padding:18px 20px;border-bottom:1px solid #ead8c0;">
                                        <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#8a5b1f;">Resumen de orden</div>
                                        <div style="font-size:20px;font-weight:bold;margin-top:8px;">#{{ $order->id }}</div>
                                        <div style="font-size:14px;margin-top:6px;">{{ $event->name }}</div>
                                        <div style="font-size:13px;color:#6e4a23;margin-top:4px;">{{ $event->venue }}{{ $event->city ? ', '.$event->city : '' }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <div style="font-size:14px;margin-bottom:6px;"><strong>Total pagado:</strong> ${{ number_format($order->total, 2) }} MXN</div>
                                        <div style="font-size:14px;"><strong>Correo de compra:</strong> {{ $order->buyer_email }}</div>
                                    </td>
                                </tr>
                            </table>

                            <div style="margin-top:24px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#8a5b1f;">Boletos</div>

                            @foreach ($tickets as $ticket)
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin-top:12px;border:1px solid #ead8c0;">
                                <tr>
                                    <td style="padding:16px 18px;">
                                        <div style="font-size:16px;font-weight:bold;">{{ $ticket->ticket_code }}</div>
                                        <div style="font-size:13px;color:#6e4a23;margin-top:6px;">Zona: {{ $ticket->zone?->name ?? 'General' }}</div>
                                        <div style="font-size:13px;color:#6e4a23;margin-top:4px;">Asiento: {{ $ticket->seat ?: 'General' }}</div>
                                        <div style="font-size:13px;color:#6e4a23;margin-top:4px;">Precio: ${{ number_format($ticket->item?->unit_price ?? 0, 2) }} MXN</div>
                                        @if ($downloadBaseUrl !== '')
                                        <div style="margin-top:10px;">
                                            <a href="{{ $downloadBaseUrl }}/api/tickets/{{ urlencode($ticket->ticket_code) }}/pdf" style="color:#9f1217;font-weight:bold;text-decoration:none;">Descargar PDF individual</a>
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            @endforeach

                            <p style="margin:24px 0 0 0;font-size:13px;line-height:1.6;color:#6e4a23;">Presenta el PDF o el codigo del boleto en el acceso. Si necesitas recuperar un ticket mas adelante, tambien puedes buscarlo en el apartado de impresion del sitio.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>