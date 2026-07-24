@extends('emails.layout')

@section('content')
    <span class="pill">Seu ingresso</span>
    <h2 class="title">Seu ingresso está pronto</h2>
    <p class="lead">Olá, <b>{{ $ticket->participant_name }}</b>! Este é o seu ingresso para o <b>{{ $ticket->event?->name }}</b>. Apresente o QR code abaixo na entrada do evento.</p>

    <div class="panel" style="border-left-color:#C9A24B;">
        <p class="label" style="margin:0 0 4px;">Titular do ingresso</p>
        <p style="font-family:Georgia,serif; font-size:20px; color:#17357A; font-weight:600; margin:0;">{{ $ticket->participant_name }}</p>
        <p style="margin:8px 0 0;"><span class="pill">{{ $ticket->ticketType?->name }}</span></p>

        <div class="divi"></div>
        <div class="row"><span class="k">Data</span><span class="v">{{ optional($ticket->event?->starts_at)->format('d/m/Y · H:i') ?: 'A confirmar' }}</span></div>
        @if($ticket->event?->location)
            <div class="row"><span class="k">Local</span><span class="v">{{ $ticket->event->location }}</span></div>
        @endif
        @if($ticket->ticketLot?->name)
            <div class="row"><span class="k">Lote</span><span class="v">{{ $ticket->ticketLot->name }}</span></div>
        @endif
        <div class="row"><span class="k">Valor</span><span class="v">{{ $ticket->is_courtesy ? 'Cortesia' : 'R$ '.number_format((float) $ticket->unit_price, 2, ',', '.') }}</span></div>
    </div>

    <div style="text-align:center; margin:0 0 22px;">
        <div style="display:inline-block; padding:14px; background:#fff; border:1px solid #E4E9F2; border-radius:12px;">
            <img src="{{ $qrDataUri }}" alt="QR {{ $ticket->code }}" width="196" height="196" style="display:block; width:196px; height:196px;">
        </div>
        <p class="mono" style="font-size:15px; letter-spacing:1.5px; color:#17357A; font-weight:700; margin:12px 0 2px;">{{ $ticket->code }}</p>
        <p style="color:#7c88a0; font-size:12px; margin:2px 0 0;">Apresente este QR code na entrada do evento.</p>
    </div>

    <div style="padding:11px 14px; background:#EAF3EC; border:1px solid #CDE6D3; border-radius:9px; margin:0 0 8px;">
        <span style="font-size:11.5px; color:#2c5138; line-height:1.45;"><b>Ingresso válido.</b> O QR Code possui assinatura digital e será validado pela recepção do evento no momento do check-in.</span>
    </div>

    <div class="cta" style="margin-top:18px;"><a href="{{ $entrarUrl }}">Acessar minha conta</a></div>
@endsection
