<?php

return [
    // Idioma base do CMS/landing (spec 013): PT é sempre a fonte.
    'base_locale' => 'pt',

    // Idiomas suportados pela landing pública (seletor PT/EN/ES).
    'locales' => ['pt', 'en', 'es'],

    // Provedor de tradução automática ao salvar. Null = preenchimento manual
    // (salvar nunca falha). Um provedor real fica atrás de TranslationProviderContract
    // e recebe credenciais via .env — nunca no VCS (constituição IV).
    'translation' => [
        'provider' => env('SITE_TRANSLATION_PROVIDER'), // null por padrão
    ],
];
