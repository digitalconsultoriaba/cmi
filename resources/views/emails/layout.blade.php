<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>@yield('subject', 'Seminário Internacional 2026')</title>
    <style>
        body { margin: 0; background: #e7ecf4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; }
        .outer { width: 100%; background: #e7ecf4; padding: 32px 12px; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 20px 50px -28px rgba(12,24,48,.5); }
        .band { background: #17357A; padding: 22px 28px; }
        .band .brandrow td { vertical-align: middle; }
        .band .logocell { width: 118px; }
        .band .brand { height: 52px; width: auto; }
        .band .titlecell { padding-left: 16px; }
        .band .event { margin: 0; color: #eaf0fb; font-family: Georgia, serif; font-size: 18px; font-weight: 600; line-height: 1.2; letter-spacing: .5px; }
        .rule-gold { height: 3px; background: #C9A24B; }
        .body { padding: 34px 40px 8px; color: #1F2A44; }
        .pill { display: inline-block; background: #F3EEDD; color: #8A6D1F; font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; padding: 6px 12px; border-radius: 999px; }
        h2.title { font-family: Georgia, 'Times New Roman', serif; font-weight: 600; color: #17357A; font-size: 24px; line-height: 1.2; margin: 16px 0 10px; }
        p.lead { font-size: 15px; line-height: 1.6; margin: 0 0 22px; color: #33405c; }
        p.lead b { color: #1F2A44; }
        .panel { background: #EEF2F8; border: 1px solid #DCE3EF; border-left: 3px solid #C9A24B; border-radius: 10px; padding: 18px 20px; margin: 0 0 24px; }
        .row { display: table; width: 100%; padding: 5px 0; }
        .row .k { display: table-cell; color: #6B7A96; font-size: 12px; text-transform: uppercase; letter-spacing: .08em; }
        .row .v { display: table-cell; text-align: right; color: #1F2A44; font-size: 14px; font-weight: 600; }
        .mono { font-family: 'SF Mono', ui-monospace, Menlo, monospace; letter-spacing: .5px; }
        .num { font-variant-numeric: tabular-nums lining-nums; }
        .divi { height: 1px; background: #DCE3EF; margin: 12px 0; }
        .label { color: #6B7A96; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; font-weight: 700; margin: 2px 0 8px; }
        .cta { text-align: center; margin: 6px 0 22px; }
        .cta a { display: inline-block; background: #17357A; color: #ffffff; text-decoration: none; font-size: 14.5px; font-weight: 600; letter-spacing: .2px; padding: 14px 32px; border-radius: 10px; }
        .note { font-size: 13px; line-height: 1.55; color: #5a6b86; margin: 0 0 8px; padding: 12px 14px; background: #FBF7EC; border: 1px solid #EFE4C6; border-radius: 8px; }
        .note b { color: #1F2A44; }
        .secondary { font-size: 12.5px; color: #7c88a0; line-height: 1.55; margin: 14px 0 2px; }
        .secondary a { color: #17357A; }
        .foot { padding: 22px 40px 30px; text-align: center; }
        .foot .hr { height: 1px; background: #E4E9F2; margin: 0 0 18px; }
        .foot .org { font-family: Georgia, serif; color: #17357A; font-size: 13.5px; font-weight: 600; margin: 0 0 4px; }
        .foot .meta { color: #9aa6bd; font-size: 11.5px; line-height: 1.6; margin: 0; }
        @media (max-width: 560px) { .body, .foot { padding-left: 22px !important; padding-right: 22px !important; } }
    </style>
</head>
<body>
    <div class="outer">
        <div class="card">
            <div class="band">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="brandrow"><tr>
                    <td class="logocell"><img class="brand" src="{{ rtrim(config('app.frontend_url'), '/').'/logo.png' }}" alt="CMI · GLMEES"></td>
                    <td class="titlecell"><div class="event">SEMINÁRIO<br>INTERNACIONAL<br>DA MAÇONARIA 2026</div></td>
                </tr></table>
            </div>
            <div class="rule-gold"></div>

            <div class="body">
                @yield('content')
            </div>

            <div class="foot">
                <div class="hr"></div>
                <p class="secondary" style="margin:0 0 16px; text-align:center;">Não reconhece esta inscrição? Se você não comprou nem foi incluído em um pedido do Seminário, ignore este e-mail, nenhuma ação é necessária.</p>
                <p class="org">Grande Loja Maçônica do Estado do Espírito Santo</p>
                <p class="meta">Este é um e-mail automático, por favor não responda.<br>Dúvidas: glmees@glmees.org.br</p>
            </div>
        </div>
    </div>
</body>
</html>
