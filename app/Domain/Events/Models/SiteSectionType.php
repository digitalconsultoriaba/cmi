<?php

namespace App\Domain\Events\Models;

/**
 * Tipos de seção do Site (spec 013). Uma seção por tipo por site. As "dinâmicas"
 * agregam N itens ordenáveis em event_site_items; as demais guardam tudo no payload.
 */
class SiteSectionType
{
    public const CONFIG = 'config';
    public const NAVBAR = 'navbar';
    public const HERO = 'hero';
    public const STATS = 'stats';
    public const ABOUT = 'about';
    public const PILLARS = 'pillars';
    public const SPEAKERS = 'speakers';
    public const PROGRAM = 'program';
    public const LOCAL = 'local';
    public const INFO = 'info';
    public const SPONSORS = 'sponsors';
    public const TESTIMONIALS = 'testimonials';
    public const FAQ = 'faq';
    public const CTA = 'cta';
    public const FOOTER = 'footer';
    public const LEGAL = 'legal';

    /** Todas as seções, na ordem em que aparecem na landing. */
    public const ALL = [
        self::CONFIG, self::NAVBAR, self::HERO, self::STATS, self::ABOUT, self::PILLARS,
        self::SPEAKERS, self::PROGRAM, self::LOCAL, self::INFO, self::SPONSORS,
        self::TESTIMONIALS, self::FAQ, self::CTA, self::FOOTER, self::LEGAL,
    ];

    /** Seções que usam listas dinâmicas de itens (event_site_items). */
    public const DYNAMIC = [
        self::STATS, self::PILLARS, self::SPEAKERS, self::PROGRAM,
        self::INFO, self::SPONSORS, self::TESTIMONIALS, self::FAQ,
    ];

    public static function all(): array
    {
        return self::ALL;
    }

    public static function dynamic(): array
    {
        return self::DYNAMIC;
    }

    public static function isDynamic(string $type): bool
    {
        return in_array($type, self::DYNAMIC, true);
    }

    /** Config não renderiza na landing; guarda os campos-mestre do site. */
    public static function renderable(): array
    {
        return array_values(array_filter(self::ALL, fn ($t) => $t !== self::CONFIG));
    }
}
