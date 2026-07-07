<?php

namespace App\Domain\Events\Services\Translation;

/**
 * Motor de i18n do Site (spec 013). Campos traduzíveis são mapas de locale
 * `{ pt, en, es }` embutidos no payload JSON; os demais campos são escalares
 * (não traduzíveis). Em vez de um schema estático por seção, detectamos um
 * locale-map pela forma: array associativo cujas chaves ⊆ idiomas suportados
 * e que contém o idioma base. Assim o walker cobre qualquer aninhamento
 * (anchors[].label, activities[].text, parágrafos legais, etc.).
 *
 * O valor de um locale pode ser string OU array de strings (ex.: parágrafos,
 * pilares.items) — a tradução preserva a estrutura.
 */
class LocaleMap
{
    public static function base(): string
    {
        return (string) config('site.base_locale', 'pt');
    }

    /** @return string[] */
    public static function supported(): array
    {
        return (array) config('site.locales', ['pt', 'en', 'es']);
    }

    /** Um valor é locale-map quando parece `{pt: ..., en: ...}`. */
    public static function isLocaleMap(mixed $value): bool
    {
        if (! is_array($value) || $value === []) {
            return false;
        }

        // Precisa ser associativo (chaves de idioma), não lista.
        if (array_is_list($value)) {
            return false;
        }

        $supported = self::supported();
        foreach (array_keys($value) as $key) {
            if (! in_array($key, $supported, true)) {
                return false;
            }
        }

        return array_key_exists(self::base(), $value);
    }

    /**
     * Percorre o payload preenchendo os idiomas ativos (≠ base) vazios a partir
     * do base via provider. Nunca falha se o provider estiver indisponível.
     *
     * @param  string[]  $activeLocales
     */
    public static function fill(mixed $payload, array $activeLocales, TranslationProviderContract $provider): mixed
    {
        if (self::isLocaleMap($payload)) {
            return self::fillMap($payload, $activeLocales, $provider);
        }

        if (is_array($payload)) {
            $out = [];
            foreach ($payload as $key => $value) {
                $out[$key] = self::fill($value, $activeLocales, $provider);
            }

            return $out;
        }

        return $payload;
    }

    private static function fillMap(array $map, array $activeLocales, TranslationProviderContract $provider): array
    {
        $base = self::base();
        $source = $map[$base] ?? null;

        foreach ($activeLocales as $locale) {
            if ($locale === $base) {
                continue;
            }

            $current = $map[$locale] ?? null;
            if (! self::isEmpty($current) || self::isEmpty($source)) {
                continue; // já preenchido, ou nada a traduzir
            }

            $map[$locale] = self::translateValue($source, $base, $locale, $provider);
        }

        return $map;
    }

    private static function translateValue(mixed $source, string $from, string $to, TranslationProviderContract $provider): mixed
    {
        if (is_array($source)) {
            return array_map(fn ($item) => self::translateValue($item, $from, $to, $provider), $source);
        }

        try {
            $result = $provider->translate((string) $source, $from, $to);
        } catch (\Throwable) {
            $result = '';
        }

        return $result;
    }

    /**
     * Achata o payload para um idioma, com fallback no base. Usado na landing.
     */
    public static function resolve(mixed $payload, string $locale): mixed
    {
        if (self::isLocaleMap($payload)) {
            $base = self::base();
            $value = $payload[$locale] ?? null;

            if (self::isEmpty($value)) {
                $value = $payload[$base] ?? null;
            }

            return $value;
        }

        if (is_array($payload)) {
            $out = [];
            foreach ($payload as $key => $value) {
                $out[$key] = self::resolve($value, $locale);
            }

            return $out;
        }

        return $payload;
    }

    private static function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return true;
        }

        if (is_array($value)) {
            return count(array_filter($value, fn ($v) => ! self::isEmpty($v))) === 0;
        }

        return false;
    }
}
