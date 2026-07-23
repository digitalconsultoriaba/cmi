@extends('emails.layout')

@section('content')
    <span class="pill">Sua conta de acesso</span>
    <h2 class="title">Bem-vindo! Sua conta foi criada</h2>
    <p class="lead">Olá, <b>{{ $user->name }}</b>! Criamos uma conta para você acompanhar sua inscrição{!! $eventName ? ' no <b>'.e($eventName).'</b>' : '' !!} — acessar seu ingresso, o QR code de entrada e as informações do evento. É só clicar no botão abaixo: você <b>entra direto</b>, sem digitar nada.</p>

    <div class="cta"><a href="{{ $magicUrl }}">Entrar na minha conta</a></div>
    <p class="secondary" style="text-align:center; margin:0 0 22px;">O botão faz login automático (válido por 14 dias). No 1º acesso você define sua própria senha.</p>

    <div class="panel">
        <p class="label" style="margin:0 0 12px;">Ou entre manualmente</p>
        <div style="padding:2px 0;">
            <p class="label" style="margin:0 0 4px;">Seu login (e-mail)</p>
            <p style="color:#1F2A44; font-size:15px; font-weight:600; word-break:break-all; margin:0;">{{ $user->email }}</p>
        </div>
        <div class="divi"></div>
        <div style="padding:2px 0;">
            <p class="label" style="margin:0 0 4px;">Senha temporária</p>
            <p class="mono num" style="font-size:24px; letter-spacing:4px; font-weight:700; color:#17357A; background:#fff; border:1px dashed #C9A24B; border-radius:8px; padding:12px 16px; text-align:center; margin:0;">{{ $password }}</p>
        </div>
        <p style="color:#8A6D1F; font-size:12px; margin:10px 0 0; line-height:1.45;">&#128274; Em <a href="{{ $entrarUrl }}" style="color:#17357A; font-weight:700; text-decoration:underline;">{{ preg_replace('#^https?://#', '', $entrarUrl) }}</a>, informe o e-mail e a senha acima. Por segurança, <b>altere a senha no primeiro acesso</b> e nunca compartilhe estes dados.</p>
    </div>

    <p class="note">Não reconhece esta inscrição? Se você não comprou nem foi incluído em um pedido do Seminário, ignore este e-mail — nenhuma ação é necessária.</p>
@endsection
