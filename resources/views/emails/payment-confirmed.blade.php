@extends('emails.layout')

@section('content')
    @php
        $method = $payment?->method === 'pix' ? 'PIX' : 'Cartão de crédito';
        $brand = $payment?->card_brand ? ' · '.ucfirst(strtolower($payment->card_brand)) : '';
        $inst = (int) ($payment?->installments ?? 1);
    @endphp

    <span class="pill">&#10003; Pagamento confirmado</span>
    <h2 class="title">Sua inscrição está confirmada</h2>
    <p class="lead">Olá, <b>{{ $order->buyer_name }}</b>! Recebemos seu pagamento com sucesso. Sua inscrição no <b>{{ $order->event?->name }}</b> está confirmada, e o ingresso de cada participante já está disponível. Guarde este e-mail: o comprovante de compra segue anexo.</p>

    <div class="panel">
        <div class="row"><span class="k">Pedido</span><span class="v mono">{{ $order->code }}</span></div>
        <div class="row"><span class="k">Data</span><span class="v num">{{ optional($payment?->paid_at ?? $order->updated_at)->format('d/m/Y · H:i') }}</span></div>
        <div class="row"><span class="k">Pagamento</span><span class="v">{{ $method }}{{ $brand }}</span></div>
        @if($payment?->method === 'card')
            <div class="row"><span class="k">Parcelamento</span><span class="v num">{{ $inst > 1 ? 'em '.$inst.'×' : 'à vista' }}</span></div>
        @endif

        <div class="divi"></div>
        <div class="label">Participantes</div>
        @foreach($order->tickets as $ticket)
            <div style="font-size:14px; color:#1F2A44; line-height:1.5; padding:3px 0;">
                {{ $ticket->participant_name }}<br>
                <span style="color:#6B7A96;">{{ $ticket->ticketType?->name }}@if($ticket->ticketLot?->name) · {{ $ticket->ticketLot->name }}@endif</span>
                · <span class="mono" style="font-size:12px; color:#17357A;">{{ $ticket->code }}</span>
            </div>
        @endforeach

        <div class="divi"></div>
        <div style="display:table; width:100%;">
            <span style="display:table-cell; color:#1F2A44; font-size:13px; font-weight:700;">Total pago</span>
            <span style="display:table-cell; text-align:right; color:#17357A; font-size:22px; font-weight:800;" class="num">R$ {{ number_format((float) $order->total_amount, 2, ',', '.') }}</span>
        </div>
    </div>

    <div class="cta"><a href="{{ $entrarUrl }}">Acessar meus ingressos</a></div>

    <p class="note"><b>Comprovante de compra (PDF)</b> anexado a este e-mail. Cada participante recebe também o próprio ingresso, com QR code, por e-mail.</p>

    <p class="secondary">Prefere consultar depois? Acompanhe seus pedidos pelo CPF em <a href="{{ $trackUrl }}">{{ preg_replace('#^https?://#', '', $trackUrl) }}</a>.</p>
@endsection
