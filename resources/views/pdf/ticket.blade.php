<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { font-family: DejaVu Sans, sans-serif; color: #1F2A44; font-size: 12px; margin: 0; }
        .header { background: #17357A; color: #fff; padding: 20px 28px; }
        .header .brand { height: 46px; }
        .tag { display: inline-block; padding: 3px 12px; border-radius: 12px; background: #C9A24B; color: #17357A; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; }
        .title { font-size: 19px; font-weight: bold; margin: 12px 0 2px; }
        .subtitle { color: #C6D4EF; font-size: 11.5px; }
        .content { padding: 20px 28px; }
        .pname { font-family: Georgia, 'DejaVu Serif', serif; font-size: 20px; color: #17357A; font-weight: bold; margin: 0 0 2px; }
        .pcat { color: #6B7A90; font-size: 12px; margin: 0 0 6px; }
        h2 { font-size: 11px; color: #17357A; text-transform: uppercase; letter-spacing: .5px; margin: 14px 0 6px; border-bottom: 1px solid #E6ECF5; padding-bottom: 5px; }
        table.kv { width: 100%; }
        table.kv td { padding: 3px 0; vertical-align: top; }
        table.kv td.label { color: #6B7A90; width: 34%; }
        .qrbox { text-align: center; margin: 18px 0 4px; }
        .qrbox img { width: 200px; height: 200px; }
        .code { font-family: DejaVu Sans Mono, monospace; letter-spacing: 1.5px; font-weight: bold; color: #17357A; font-size: 15px; text-align: center; margin: 6px 0 2px; }
        .hint { text-align: center; color: #7c88a0; font-size: 11px; margin: 0; }
        .valid { margin: 16px 28px 0; background: #EAF5EE; border: 1px solid #CBE7D5; border-radius: 8px; padding: 10px 14px; color: #2c5138; font-size: 11px; line-height: 1.45; }
        .footer { padding: 14px 28px; color: #6B7A90; font-size: 10.5px; border-top: 1px solid #E6ECF5; }
    </style>
</head>
<body>
    <div class="header">
        <table style="width:100%;"><tr>
            <td style="vertical-align:middle;">
                @if($logoData)<img class="brand" src="{{ $logoData }}" alt="logo">@endif
            </td>
            <td style="text-align:right; vertical-align:middle;"><span class="tag">Ingresso</span></td>
        </tr></table>
        <div class="title">{{ $ticket->event->name }}</div>
        <div class="subtitle">Ingresso do participante — apresente o QR code na entrada.</div>
    </div>

    <div class="content">
        <p class="pname">{{ $ticket->participant_name }}</p>
        <p class="pcat">{{ $ticket->is_courtesy ? 'Cortesia' : $ticket->ticketType?->name }}@if($ticket->ticketLot?->name) · Lote {{ $ticket->ticketLot->name }}@endif</p>

        <h2>Evento</h2>
        <table class="kv">
            <tr><td class="label">Data</td><td>{{ $ticket->event->starts_at?->format('d/m/Y H:i') ?? 'A confirmar' }}</td></tr>
            @if($ticket->event->location)<tr><td class="label">Local</td><td>{{ $ticket->event->location }}</td></tr>@endif
            <tr><td class="label">Pedido</td><td class="code" style="font-size:12px;">{{ $ticket->order?->code }}</td></tr>
        </table>

        <div class="qrbox"><img src="{{ $qrDataUri }}" alt="QR {{ $ticket->code }}"></div>
        <div class="code">{{ $ticket->code }}</div>
        <p class="hint">Apresente este QR code na entrada do evento.</p>
    </div>

    <div class="valid"><b>Ingresso válido.</b> O QR carrega o código de emissão do sistema — a portaria valida no check-in. Ingresso pessoal e intransferível.</div>

    <div class="footer">
        Ingresso emitido pelo sistema do {{ $ticket->event->name }}.<br>
        Dúvidas, entre em contato com a GLMEES.<br>
        Emitido em {{ now()->format('d/m/Y H:i') }}.
    </div>
</body>
</html>
