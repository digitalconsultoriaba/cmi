@extends('emails.layout')

@section('content')
    <span class="pill">Sua conta de acesso</span>
    <h2 class="title">Bem-vindo ao Seminário Internacional da Maçonaria 2026!</h2>
    <p class="lead">Olá, <b>{{ $user->name }}</b>,</p>
    <p class="lead">Sua conta foi criada com sucesso.</p>
    <p class="lead">A partir dela você poderá acompanhar sua inscrição, acessar seu ingresso com QR Code, consultar as informações do evento e gerenciar seus dados sempre que precisar.</p>
    <p class="lead">Para entrar pela primeira vez, basta clicar no botão abaixo. O acesso será realizado automaticamente, sem necessidade de informar login ou senha.</p>

    <div class="cta"><a href="{{ $magicUrl }}">Entrar na minha conta</a></div>
    <p class="secondary" style="text-align:center; margin:0 0 22px;">O link acima realiza o acesso automático e é válido por 14 dias. Após o primeiro acesso, você poderá definir sua própria senha para utilizar a plataforma sempre que desejar.</p>

    <div class="panel">
        <p class="label" style="margin:0 0 12px;">Caso prefira acessar manualmente</p>
        <div style="padding:2px 0;">
            <p class="label" style="margin:0 0 4px;">E-mail de acesso</p>
            <p style="color:#1F2A44; font-size:15px; font-weight:600; word-break:break-all; margin:0;">{{ $user->email }}</p>
        </div>
        <div class="divi"></div>
        <div style="padding:2px 0;">
            <p class="label" style="margin:0 0 4px;">Senha temporária</p>
            <p class="mono num" style="font-size:24px; letter-spacing:4px; font-weight:700; color:#17357A; background:#fff; border:1px dashed #C9A24B; border-radius:8px; padding:12px 16px; text-align:center; margin:0;">{{ $password }}</p>
        </div>
        <p style="color:#8A6D1F; font-size:12px; margin:10px 0 0; line-height:1.45;">Acesse em <a href="{{ $entrarUrl }}" style="color:#17357A; font-weight:700; text-decoration:underline;">{{ preg_replace('#^https?://#', '', $entrarUrl) }}</a> com o e-mail e a senha acima. Por motivos de segurança, recomendamos <b>alterar sua senha após o primeiro acesso</b>.</p>
    </div>

    <p class="lead" style="margin:22px 0 6px;">Se precisar de ajuda, nossa equipe estará à disposição.</p>
    <p class="lead" style="margin:0;">Nos vemos no Seminário Internacional da Maçonaria 2026!</p>
@endsection
