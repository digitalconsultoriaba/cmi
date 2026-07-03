<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1a1a2e; font-size: 12px; }
        .card { border: 2px solid #1a1a2e; border-radius: 8px; padding: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 16px 0 4px; color: #444; }
        .muted { color: #666; }
        .code { font-family: DejaVu Sans Mono, monospace; font-size: 14px; letter-spacing: 1px; }
        .qr { text-align: center; margin-top: 20px; }
        .badge { display: inline-block; padding: 2px 8px; border: 1px solid #1a1a2e;
                 border-radius: 10px; font-size: 10px; text-transform: uppercase; }
        table { width: 100%; margin-top: 8px; }
        td { padding: 2px 0; vertical-align: top; }
        td.label { color: #666; width: 35%; }
    </style>
</head>
<body>
<div class="card">
    <h1>{{ $ticket->event->name }}</h1>
    <div class="muted">
        {{ $ticket->event->starts_at?->format('d/m/Y H:i') }}
        @if($ticket->event->location) · {{ $ticket->event->location }} @endif
    </div>

    <h2>Comprovante de inscrição <span class="badge">{{ $ticket->is_courtesy ? 'Cortesia' : $ticket->ticketType->name }}</span></h2>
    <table>
        <tr><td class="label">Participante</td><td>{{ $ticket->participant_name }}</td></tr>
        @if($ticket->companion_name)
            <tr><td class="label">Acompanhante</td><td>{{ $ticket->companion_name }}</td></tr>
        @endif
        <tr><td class="label">Tipo</td><td>{{ $ticket->ticketType->name }}</td></tr>
        @if($ticket->shirtSize)
            <tr><td class="label">Camisa</td><td>{{ $ticket->shirtModel?->label }} — {{ $ticket->shirtSize->label }}</td></tr>
        @endif
        <tr><td class="label">Pedido</td><td class="code">{{ $ticket->order->code }}</td></tr>
        <tr><td class="label">Código do ingresso</td><td class="code">{{ $ticket->code }}</td></tr>
    </table>

    <div class="qr">
        {!! $qrSvg !!}
        <div class="muted" style="margin-top:6px;">Apresente este QR code na entrada do evento.</div>
    </div>
</div>
</body>
</html>
