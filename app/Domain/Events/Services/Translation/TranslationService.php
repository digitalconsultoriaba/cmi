<?php

namespace App\Domain\Events\Services\Translation;

/**
 * Preenche os idiomas ativos (≠ base) dos campos localizados de um payload a
 * partir do idioma base, via provider. Detecção de campos é genérica (LocaleMap),
 * então serve a qualquer seção/item sem schema estático. Nunca falha se o
 * provider estiver indisponível.
 */
class TranslationService
{
    public function __construct(private TranslationProviderContract $provider) {}

    /**
     * @param  array  $payload  payload da seção ou do item
     * @param  string[]  $activeLocales  idiomas ativos do site (inclui o base)
     */
    public function fillPayload(array $payload, array $activeLocales): array
    {
        return LocaleMap::fill($payload, $activeLocales, $this->provider);
    }
}
