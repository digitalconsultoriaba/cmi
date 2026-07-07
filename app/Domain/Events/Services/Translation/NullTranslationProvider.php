<?php

namespace App\Domain\Events\Services\Translation;

/**
 * Provedor padrão: não traduz. Os idiomas-alvo ficam vazios para preenchimento
 * manual no CMS; salvar nunca falha por ausência de provedor (spec 013, FR-013).
 */
class NullTranslationProvider implements TranslationProviderContract
{
    public function translate(string $text, string $from, string $to): string
    {
        return '';
    }
}
