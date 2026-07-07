<?php

namespace Tests\Feature\Site;

use App\Domain\Events\Services\Translation\TranslationProviderContract;

/** Provider de teste: prefixa o idioma-alvo para tornar a tradução verificável. */
class TranslationProviderFake implements TranslationProviderContract
{
    public function translate(string $text, string $from, string $to): string
    {
        return "[$to] $text";
    }
}

/** Provider que falha — para provar que salvar nunca quebra. */
class TranslationProviderBroken implements TranslationProviderContract
{
    public function translate(string $text, string $from, string $to): string
    {
        throw new \RuntimeException('provider indisponível');
    }
}
