<?php

namespace App\Http\Resources\Public;

use App\Domain\Events\Models\EventSiteItem;
use App\Domain\Events\Models\SiteSectionType;
use App\Domain\Events\Services\Translation\LocaleMap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Landing pública (spec 013): achata os campos localizados no idioma pedido
 * (fallback base), converte paths de mídia em URLs e omite seções inativas/vazias.
 */
class PublicSiteResource extends JsonResource
{
    /** Chaves cujo valor é path de imagem → vira URL absoluta. */
    private const MEDIA_KEYS = [
        'photo', 'src', 'venuePhoto', 'logoPath', 'watermarkPath', 'ogImagePath',
        'portraitLeftPhoto', 'portraitRightPhoto', 'gallery', 'sealPaths',
    ];

    public function __construct($resource, private string $lang)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $lang = $this->lang;

        return [
            'slug' => $this->slug,
            'lang' => $lang,
            'availableLanguages' => $this->activeLanguages(),
            'theme' => $this->theme ?? [],
            'identity' => $this->media(LocaleMap::resolve($this->identity ?? [], $lang)),
            'countdownAt' => $this->countdown_at?->toISOString(),
            'seo' => $this->media(LocaleMap::resolve($this->seo ?? [], $lang)),
            'sections' => $this->sectionsFor($lang),
        ];
    }

    private function sectionsFor(string $lang): array
    {
        $sections = $this->relationLoaded('sections') ? $this->sections : $this->sections()->get();

        return $sections
            ->filter(fn ($s) => $s->is_active && $s->type !== SiteSectionType::CONFIG)
            ->sortBy([['sort', 'asc'], ['id', 'asc']])
            ->map(function ($section) use ($lang) {
                $items = $this->itemsFor($section, $lang);

                // Seção dinâmica sem itens não renderiza (sem espaço vazio).
                if ($section->isDynamic() && $items === []) {
                    return null;
                }

                return [
                    'type' => $section->type,
                    'sort' => $section->sort,
                    'payload' => $this->media(LocaleMap::resolve($section->payload ?? [], $lang)),
                    'items' => $items,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function itemsFor($section, string $lang): array
    {
        if (! $section->isDynamic()) {
            return [];
        }

        $all = $section->relationLoaded('allItems') ? $section->allItems : $section->allItems()->get();
        $byParent = $all->groupBy('parent_item_id');

        $build = function ($parentId) use (&$build, $byParent, $lang) {
            return ($byParent[$parentId] ?? collect())
                ->sortBy([['sort', 'asc'], ['id', 'asc']])
                ->map(fn (EventSiteItem $item) => [
                    'payload' => $this->media(LocaleMap::resolve($item->payload ?? [], $lang)),
                    'children' => $build($item->id),
                ])->values()->all();
        };

        return $build(null);
    }

    /** Converte paths de mídia (recursivo) em URLs absolutas. */
    private function media(mixed $value, ?string $key = null): mixed
    {
        if (is_string($value) && $value !== '' && in_array($key, self::MEDIA_KEYS, true)) {
            return Storage::disk('public')->url($value);
        }

        if (is_array($value)) {
            $isMediaList = in_array($key, ['gallery', 'sealPaths'], true);
            $out = [];
            foreach ($value as $k => $v) {
                if ($isMediaList && is_string($v) && $v !== '') {
                    $out[$k] = Storage::disk('public')->url($v);
                } else {
                    $out[$k] = $this->media($v, is_string($k) ? $k : $key);
                }
            }

            return $out;
        }

        return $value;
    }
}
