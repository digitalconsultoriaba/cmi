<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { font-family: DejaVu Sans, sans-serif; color: #1F2A44; font-size: 12px; margin: 0; }
        .header { background: #17357A; color: #fff; padding: 22px 32px; }
        .header .brand { height: 52px; }
        .tag { display: inline-block; padding: 3px 12px; border-radius: 12px; background: #fff; color: #17357A; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; }
        .title { font-size: 20px; font-weight: bold; margin: 14px 0 2px; }
        .subtitle { color: #C6D4EF; font-size: 12px; }
        .content { padding: 22px 32px; }
        h2 { font-size: 12px; color: #17357A; text-transform: uppercase; letter-spacing: .5px; margin: 18px 0 6px; border-bottom: 1px solid #E6ECF5; padding-bottom: 5px; }
        table.kv { width: 100%; }
        table.kv td { padding: 3px 0; vertical-align: top; }
        table.kv td.label { color: #6B7A90; width: 32%; }
        .code { font-family: DejaVu Sans Mono, monospace; letter-spacing: 1px; font-weight: bold; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.items th { text-align: left; font-size: 10px; color: #6B7A90; text-transform: uppercase; border-bottom: 1px solid #E6ECF5; padding: 6px 4px; }
        table.items td { padding: 8px 4px; border-bottom: 1px solid #F0F3F8; }
        .right { text-align: right; }
        .total { text-align: right; font-size: 15px; font-weight: bold; margin-top: 12px; color: #17357A; }
        .footer { padding: 16px 32px; color: #6B7A90; font-size: 11px; border-top: 1px solid #E6ECF5; }
    </style>
</head>
<body>
    <div class="header">
        <table style="width:100%;"><tr>
            <td style="vertical-align:middle;">
                @if($logoData)<img class="brand" src="{{ $logoData }}" alt="logo">@endif
            </td>
            <td class="right" style="vertical-align:middle;"><span class="tag">Comprovante de compra</span></td>
        </tr></table>
        <div class="title">{{ $order->event->name }}</div>
        <div class="subtitle">Comprovante da sua inscrição — guarde este documento.</div>
    </div>

    <div class="content">
        <h2>Pedido</h2>
        <table class="kv">
            <tr><td class="label">Identificador</td><td class="code">{{ $order->code }}</td></tr>
            <tr><td class="label">Data/hora</td><td>{{ $paidAt?->format('d/m/Y H:i') }}</td></tr>
            <tr><td class="label">Situação</td><td>Pago</td></tr>
        </table>

        <h2>Pagador</h2>
        <table class="kv">
            <tr><td class="label">Nome</td><td>{{ $order->buyer_name }}</td></tr>
            <tr><td class="label">E-mail</td><td>{{ $order->buyer_email }}</td></tr>
            @if($payerDocument)<tr><td class="label">CPF/CNPJ</td><td>{{ $payerDocument }}</td></tr>@endif
        </table>

        <h2>Pagamento</h2>
        <table class="kv">
            <tr><td class="label">Forma</td><td>{{ $paymentLabel }}</td></tr>
            <tr><td class="label">Valor</td><td>R$ {{ number_format((float) $order->total_amount, 2, ',', '.') }}</td></tr>
        </table>

        <h2>Itens</h2>
        <table class="items">
            <thead><tr><th>Participante</th><th>Ingresso</th><th class="right">Valor</th></tr></thead>
            <tbody>
                @foreach($order->tickets as $ticket)
                    <tr>
                        <td>{{ $ticket->participant_name }}</td>
                        <td>{{ $ticket->is_courtesy ? 'Cortesia' : $ticket->ticketType?->name }}</td>
                        <td class="right">R$ {{ number_format((float) $ticket->unit_price, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="total">Total: R$ {{ number_format((float) $order->total_amount, 2, ',', '.') }}</div>
    </div>

    <div class="footer">
        Este é o comprovante da sua compra no {{ $order->event->name }}.
        @if($order->event->support_email) Dúvidas: {{ $order->event->support_email }}.@endif
        Documento emitido em {{ now()->format('d/m/Y H:i') }}.
    </div>
</body>
</html>
