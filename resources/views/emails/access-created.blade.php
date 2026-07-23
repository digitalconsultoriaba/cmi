@extends('emails.layout')

@section('content')
    <span class="pill">Sua conta de acesso</span>
    <h2 class="title">Bem-vindo! Sua conta foi criada</h2>
    <p class="lead">Olá, <b>{{ $user->name }}</b>! Criamos uma conta para você acompanhar sua inscrição{!! $eventName ? ' no <b>'.e($eventName).'</b>' : '' !!} — acessar seu ingresso, o QR code de entrada e as informações do evento.</p>

    <div class="panel">
        <div style="padding:6px 0;">
            <p class="label" style="margin:0 0 4px;">Seu login (e-mail)</p>
            <p style="color:#1F2A44; font-size:15px; font-weight:600; word-break:break-all; margin:0;">{{ $user->email }}</p>
        </div>
        <div class="divi"></div>
        <div style="padding:6px 0;">
            <p class="label" style="margin:0 0 4px;">Senha temporária</p>
            <p class="mono num" style="font-size:24px; letter-spacing:4px; font-weight:700; color:#17357A; background:#fff; border:1px dashed #C9A24B; border-radius:8px; padding:12px 16px; text-align:center; margin:0;">{{ $password }}</p>
        </div>
        <p style="color:#8A6D1F; font-size:12px; margin:10px 0 0; line-height:1.45;">&#128274; Por segurança, <b>altere a senha no primeiro acesso</b>. Nunca compartilhe estes dados.</p>
    </div>

    <div class="cta"><a href="{{ $entrarUrl }}">Entrar na minha conta</a></div>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; margin:0 0 18px;">
        @foreach(['Acesse <b>'.preg_replace('#^https?://#','',$entrarUrl).'</b>', 'Informe o <b>e-mail</b> e a <b>senha temporária</b> acima', 'Pronto — dentro da conta você vê seu <b>ingresso, QR code</b> e os detalhes do evento'] as $i => $step)
            <tr>
                <td style="width:34px; vertical-align:top; padding:0 0 12px;">
                    <span style="display:inline-block; width:23px; height:23px; line-height:23px; text-align:center; border-radius:50%; background:#17357A; color:#fff; font-size:12px; font-weight:700;">{{ $i + 1 }}</span>
                </td>
                <td style="vertical-align:top; padding:2px 0 12px; font-size:14px; line-height:1.5; color:#33405c;">{!! $step !!}</td>
            </tr>
        @endforeach
    </table>

    <p class="note">Não reconhece esta inscrição? Se você não comprou nem foi incluído em um pedido do Seminário, ignore este e-mail — nenhuma ação é necessária.</p>
@endsection
