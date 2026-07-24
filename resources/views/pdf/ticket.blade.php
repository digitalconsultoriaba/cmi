<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; background: #0E1524; font-family: 'DejaVu Sans', sans-serif; color: #1F2A44; font-size: 12px; }

        /* Card do ingresso, flutuando sobre o fundo escuro */
        .ticket { background: #fff; border-radius: 18px; margin: 26px 22px; position: relative; }

        .t-head { background: #17357A; color: #fff; padding: 18px 24px 20px; border-radius: 18px 18px 0 0; }
        .t-head .taghold { text-align: right; margin-bottom: 6px; }
        .t-head .tag { display: inline-block; background: #C9A24B; color: #17357A; font-size: 9.5px; letter-spacing: 1.5px; text-transform: uppercase; font-weight: bold; padding: 4px 10px; border-radius: 999px; }
        .t-head .brandrow { border-collapse: collapse; }
        .t-head .logocell { vertical-align: middle; }
        .t-head .brand { height: 52px; width: auto; }
        .t-head .titlecell { vertical-align: middle; padding-left: 16px; }
        .t-head .event { font-family: 'DejaVu Serif', Georgia, serif; font-size: 17px; font-weight: bold; color: #EAF0FB; line-height: 1.2; letter-spacing: .5px; margin: 0; }
        .rule-gold { height: 3px; background: #C9A24B; }

        .content { padding: 22px 24px 6px; }
        .label { color: #8093AD; font-size: 10.5px; letter-spacing: 1.5px; text-transform: uppercase; font-weight: bold; margin: 0 0 4px; }
        .name { font-family: 'DejaVu Serif', Georgia, serif; font-size: 23px; color: #17357A; font-weight: bold; line-height: 1.15; margin: 0; }
        .cat { display: inline-block; margin-top: 10px; font-size: 11px; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; color: #8A6D1F; background: #F3EEDD; padding: 5px 12px; border-radius: 999px; }

        table.grid { width: 100%; margin-top: 14px; border-collapse: collapse; }
        table.grid td { width: 50%; vertical-align: top; padding: 7px 0; }
        .k { color: #8093AD; font-size: 10px; letter-spacing: 1px; text-transform: uppercase; font-weight: bold; margin: 0 0 2px; }
        .v { color: #1F2A44; font-size: 13px; font-weight: bold; margin: 0; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 12px; letter-spacing: .5px; }

        /* Picote com recortes laterais (as "mordidas" do ingresso) */
        .perf { position: relative; height: 26px; margin-top: 12px; }
        .perf .dash { position: absolute; top: 12px; left: 22px; right: 22px; border-top: 2px dashed #D3DBEA; }
        .notch { position: absolute; top: 0; width: 26px; height: 26px; border-radius: 50%; background: #0E1524; }
        .notch.l { left: -13px; }
        .notch.r { right: -13px; }

        .qr { text-align: center; padding: 4px 24px 6px; }
        .qr .box { display: inline-block; padding: 12px; background: #fff; border: 1px solid #E4E9F2; border-radius: 12px; }
        .qr .box img { width: 176px; height: 176px; display: block; }
        .code { font-family: 'DejaVu Sans Mono', monospace; font-size: 15px; letter-spacing: 1.5px; color: #17357A; font-weight: bold; margin: 12px 0 2px; }
        .hint { color: #7C88A0; font-size: 12px; margin: 2px 0 0; }

        .valid { margin: 14px 24px 0; padding: 11px 14px; background: #EAF3EC; border: 1px solid #CDE6D3; border-radius: 9px; }
        .valid .dot { width: 22px; height: 22px; border-radius: 50%; background: #1E7D46; }
        .valid .dotc { width: 22px; height: 22px; border-collapse: collapse; }
        .valid .dotc td { text-align: center; vertical-align: middle; color: #fff; font-size: 14px; font-weight: bold; line-height: 1; }
        .valid .txt { font-size: 11px; color: #2C5138; line-height: 1.45; }

        .foot { padding: 16px 24px 22px; text-align: center; border-radius: 0 0 18px 18px; }
        .foot p { color: #9AA6BD; font-size: 10.5px; line-height: 1.55; margin: 0; }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="t-head">
            <div class="taghold"><span class="tag">Ingresso</span></div>
            <table class="brandrow"><tr>
                <td class="logocell">@if($logoData)<img class="brand" src="{{ $logoData }}" alt="logo">@endif</td>
                <td class="titlecell"><div class="event">SEMINÁRIO<br>INTERNACIONAL<br>DA MAÇONARIA 2026</div></td>
            </tr></table>
        </div>
        <div class="rule-gold"></div>

        <div class="content">
            <p class="label">Titular do ingresso</p>
            <p class="name">{{ $ticket->participant_name }}</p>
            <span class="cat">{{ $ticket->is_courtesy ? 'Cortesia' : $ticket->ticketType?->name }}</span>

            <table class="grid">
                <tr>
                    <td><div class="k">Data</div><div class="v">{{ $ticket->event->starts_at?->format('d/m/Y · H\hi') ?? 'A confirmar' }}</div></td>
                    <td><div class="k">Local</div><div class="v">{{ $ticket->event->location ?? 'A confirmar' }}</div></td>
                </tr>
                <tr>
                    <td><div class="k">Lote</div><div class="v">{{ $ticket->ticketLot?->name ?? '—' }}</div></td>
                    <td><div class="k">Pedido</div><div class="v mono">{{ $ticket->order?->code }}</div></td>
                </tr>
                <tr>
                    <td><div class="k">Valor</div><div class="v">{{ $ticket->is_courtesy ? 'Cortesia' : 'R$ '.number_format((float) $ticket->unit_price, 2, ',', '.') }}</div></td>
                    <td><div class="k">Emitido em</div><div class="v">{{ now()->format('d/m/Y') }}</div></td>
                </tr>
            </table>
        </div>

        <div class="perf">
            <div class="notch l"></div>
            <div class="notch r"></div>
            <div class="dash"></div>
        </div>

        <div class="qr">
            <div class="box"><img src="{{ $qrDataUri }}" alt="QR {{ $ticket->code }}"></div>
            <div class="code">{{ $ticket->code }}</div>
            <p class="hint">Apresente este QR code na entrada do evento.</p>
        </div>

        <div class="valid">
            <table style="width:100%;"><tr>
                <td style="width:32px; vertical-align:top;"><div class="dot"><table class="dotc"><tr><td>&#10003;</td></tr></table></div></td>
                <td class="txt"><b>Ingresso válido.</b> O QR Code possui assinatura digital e será validado pela recepção do evento no momento do check-in.</td>
            </tr></table>
        </div>

        <div class="foot">
            <p>Ingresso pessoal e intransferível, vinculado ao titular acima.<br>Grande Loja Maçônica do Estado do Espírito Santo · glmees@glmees.org.br</p>
        </div>
    </div>
</body>
</html>
