<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { font-family: DejaVu Sans, sans-serif; color: #1F2A44; font-size: 12px; margin: 0; }
        .header { background: #17357A; color: #fff; padding: 18px 32px 20px; }
        .header .taghold { text-align: right; margin-bottom: 4px; }
        .tag { display: inline-block; padding: 4px 12px; border-radius: 999px; background: #C9A24B; color: #17357A; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; }
        .header .brandrow td { vertical-align: middle; }
        .header .logocell { width: 120px; }
        .header .brand { height: 50px; }
        .header .titlecell { padding-left: 16px; }
        .header .ev { font-family: 'DejaVu Serif', Georgia, serif; font-size: 17px; font-weight: bold; color: #EAF0FB; line-height: 1.2; letter-spacing: .5px; }
        .rule-gold { height: 3px; background: #C9A24B; }
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
        <div class="taghold"><span class="tag">Comprovante de pagamento</span></div>
        <table class="brandrow" style="width:100%;"><tr>
            <td class="logocell">@if($logoData)<img class="brand" src="{{ $logoData }}" alt="logo">@endif</td>
            <td class="titlecell"><div class="ev">SEMINÁRIO<br>INTERNACIONAL<br>DA MAÇONARIA 2026</div></td>
        </tr></table>
    </div>
    <div class="rule-gold"></div>

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
        Este é o comprovante de pagamento do seu pedido no Seminário Internacional da Maçonaria 2026.
        Dúvidas: glmees@glmees.org.br.
        Documento emitido em {{ now()->format('d/m/Y H:i') }}.
    </div>
</body>
</html>
