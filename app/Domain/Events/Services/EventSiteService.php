<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventSite;
use App\Domain\Events\Models\EventSiteSection;
use App\Domain\Events\Models\SiteSectionType;
use App\Domain\Events\Services\Translation\TranslationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gestão do Site do evento (spec 013): criação sob demanda, config/tema/SEO,
 * publicação e seções. Escritas multi-passo em transação.
 */
class EventSiteService
{
    public function __construct(private TranslationService $translations) {}

    /** Cria o site (idempotente) com todas as seções default. Devolve o site. */
    public function ensureSite(Event $event): EventSite
    {
        $site = $event->eventSite()->first();
        if ($site !== null) {
            return $this->ensureSections($site);
        }

        return DB::transaction(function () use ($event) {
            $site = $event->eventSite()->create([
                'slug' => $this->suggestSlug($event),
                'theme' => self::defaultTheme(),
                'identity' => ['eventName' => $event->name, 'logoPath' => null, 'sealPaths' => [], 'watermarkPath' => null],
                'countdown_at' => $event->starts_at,
                'seo' => ['title' => ['pt' => $event->name], 'description' => ['pt' => (string) $event->description], 'ogImagePath' => null],
                'active_languages' => [config('site.base_locale', 'pt')],
            ]);

            return $this->ensureSections($site);
        });
    }

    /** Garante uma linha por tipo de seção (idempotente). */
    public function ensureSections(EventSite $site): EventSite
    {
        $existing = $site->sections()->pluck('type')->all();

        foreach (SiteSectionType::all() as $index => $type) {
            if (in_array($type, $existing, true)) {
                continue;
            }
            $site->sections()->create([
                'type' => $type,
                'sort' => $index,
                'is_active' => $type !== SiteSectionType::CONFIG,
                'payload' => self::defaultPayload($type),
            ]);
        }

        return $site->load('sections.allItems');
    }

    /** Atualiza config/tema/SEO/idiomas/slug/data (SEO passa pela tradução). */
    public function updateConfig(EventSite $site, array $data): EventSite
    {
        return DB::transaction(function () use ($site, $data) {
            $attrs = [];

            if (array_key_exists('slug', $data)) {
                $attrs['slug'] = Str::slug($data['slug']);
            }
            if (array_key_exists('theme', $data)) {
                $attrs['theme'] = $data['theme'];
            }
            if (array_key_exists('identity', $data)) {
                $attrs['identity'] = $data['identity'];
            }
            if (array_key_exists('countdownAt', $data)) {
                $attrs['countdown_at'] = $data['countdownAt'] ?: null;
            }
            if (array_key_exists('activeLanguages', $data)) {
                $attrs['active_languages'] = $this->normalizeLanguages($data['activeLanguages']);
            }

            $active = $attrs['active_languages'] ?? $site->activeLanguages();

            if (array_key_exists('seo', $data)) {
                $attrs['seo'] = $this->translations->fillPayload((array) $data['seo'], $active);
            }

            $site->update($attrs);

            return $site->fresh('sections.allItems');
        });
    }

    public function publish(EventSite $site): EventSite
    {
        $site->publish();

        return $site->fresh('sections.allItems');
    }

    public function unpublish(EventSite $site): EventSite
    {
        $site->unpublish();

        return $site->fresh('sections.allItems');
    }

    /** Atualiza payload/is_active de uma seção (traduz campos localizados). */
    public function upsertSection(EventSiteSection $section, array $data): EventSiteSection
    {
        return DB::transaction(function () use ($section, $data) {
            $active = $section->site->activeLanguages();
            $attrs = [];

            if (array_key_exists('payload', $data)) {
                $attrs['payload'] = $this->translations->fillPayload((array) $data['payload'], $active);
            }
            if (array_key_exists('isActive', $data)) {
                $attrs['is_active'] = (bool) $data['isActive'];
            }

            $section->update($attrs);

            return $section->fresh('allItems');
        });
    }

    /** @param  int[]  $order  ids de seção na nova ordem */
    public function reorderSections(EventSite $site, array $order): void
    {
        DB::transaction(function () use ($site, $order) {
            foreach ($order as $index => $id) {
                $site->sections()->where('id', $id)->update(['sort' => $index]);
            }
        });
    }

    private function suggestSlug(Event $event): string
    {
        $base = $event->slug ?: Str::slug($event->name);
        $slug = $base;
        $n = 2;
        while (EventSite::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    private function normalizeLanguages(mixed $langs): array
    {
        $base = (string) config('site.base_locale', 'pt');
        $supported = (array) config('site.locales', ['pt', 'en', 'es']);
        $langs = array_values(array_intersect((array) $langs, $supported));
        if (! in_array($base, $langs, true)) {
            array_unshift($langs, $base);
        }

        return array_values(array_unique($langs));
    }

    public static function defaultTheme(): array
    {
        return [
            'bg' => '#071E2E', 'bgMid' => '#081127', 'bgEnd' => '#0B1533',
            'surface' => '#191376', 'slate' => '#466089',
            'accent' => '#CCA54D', 'accentHover' => '#E3C173',
            'textLight' => '#EAECF0', 'textTitle' => '#F1F3F7', 'textCream' => '#F5F1E6',
            'textMuted' => '#B9C6D4', 'textMuted2' => '#8FA2B6', 'blue' => '#7FA0C8',
        ];
    }

    /** Payload inicial vazio (mas com a forma esperada) por tipo de seção. */
    public static function defaultPayload(string $type): array
    {
        return match ($type) {
            SiteSectionType::CONFIG => [],
            SiteSectionType::NAVBAR => ['ctaLabel' => ['pt' => 'Inscreva-se'], 'ctaHref' => '', 'anchors' => []],
            SiteSectionType::HERO => ['titleLine1' => ['pt' => ''], 'titleLine2' => ['pt' => ''], 'titleLine3' => ['pt' => ''], 'subtitle' => ['pt' => ''], 'dateText' => ['pt' => ''], 'locationText' => ['pt' => ''], 'primaryLabel' => ['pt' => 'Inscreva-se'], 'primaryHref' => '', 'secondaryLabel' => ['pt' => ''], 'secondaryHref' => ''],
            SiteSectionType::ABOUT => ['aboutLabel' => ['pt' => ''], 'aboutTitle' => ['pt' => ''], 'aboutText' => ['pt' => ''], 'aboutBtn' => ['pt' => ''], 'aboutHref' => '', 'gallery' => []],
            SiteSectionType::SPEAKERS => ['speakersLabel' => ['pt' => 'Palestrantes'], 'speakersBtn' => ['pt' => ''], 'speakersHref' => ''],
            SiteSectionType::PROGRAM => ['progLabel' => ['pt' => 'Programação'], 'progBtn' => ['pt' => ''], 'progHref' => ''],
            SiteSectionType::LOCAL => ['localLabel' => ['pt' => 'Local'], 'placeName' => '', 'localText' => ['pt' => ''], 'localBtn' => ['pt' => ''], 'localHref' => '', 'venueName' => '', 'venueAddress' => ['pt' => ''], 'mapBtn' => ['pt' => 'Ver no mapa'], 'mapHref' => '', 'venuePhoto' => null],
            SiteSectionType::INFO => ['infoLabel' => ['pt' => 'Informações']],
            SiteSectionType::SPONSORS => ['sponsorsLabel' => ['pt' => 'Patrocinadores']],
            SiteSectionType::TESTIMONIALS => ['testiLabel' => ['pt' => 'Depoimentos']],
            SiteSectionType::FAQ => ['faqLabel' => ['pt' => 'Perguntas frequentes']],
            SiteSectionType::STATS => [],
            SiteSectionType::PILLARS => ['pillarsLabel' => ['pt' => '']],
            SiteSectionType::CTA => ['ctaKicker' => ['pt' => ''], 'ctaTitle' => ['pt' => 'Inscrições Abertas'], 'ctaSubtitle' => ['pt' => ''], 'ctaDate' => ['pt' => ''], 'ctaLocation' => ['pt' => ''], 'ctaBtnLabel' => ['pt' => 'Inscreva-se'], 'ctaBtnHref' => ''],
            SiteSectionType::FOOTER => ['footerTagline' => ['pt' => ''], 'contactEmail' => '', 'contactPhone' => '', 'contactWhatsapp' => '', 'whatsappHref' => '', 'instagramHref' => '', 'facebookHref' => '', 'youtubeHref' => '', 'linkedinHref' => '', 'copyright' => ['pt' => ''], 'privacyHref' => '', 'termsHref' => ''],
            SiteSectionType::LEGAL => ['privacy' => ['title' => ['pt' => 'Política de Privacidade'], 'paragraphs' => ['pt' => []]], 'terms' => ['title' => ['pt' => 'Termos de Uso'], 'paragraphs' => ['pt' => []]]],
            default => [],
        };
    }
}
