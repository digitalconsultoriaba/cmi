<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EventSiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'eventId' => $this->event_id,
            'slug' => $this->slug,
            'theme' => $this->theme ?? [],
            'identity' => $this->identityWithUrls(),
            'countdownAt' => $this->countdown_at?->toISOString(),
            'seo' => $this->seo ?? [],
            'activeLanguages' => $this->activeLanguages(),
            'supportedLanguages' => (array) config('site.locales', ['pt', 'en', 'es']),
            'baseLanguage' => (string) config('site.base_locale', 'pt'),
            'isPublished' => $this->isPublished(),
            'publishedAt' => $this->published_at?->toISOString(),
            'publicUrl' => '/site/'.$this->slug,
            'sections' => SiteSectionResource::collection($this->whenLoaded('sections')),
        ];
    }

    /** Acrescenta URLs absolutas aos paths de identidade (logo/selos/marca). */
    private function identityWithUrls(): array
    {
        $identity = $this->identity ?? [];
        $url = fn (?string $p) => $p ? Storage::disk('public')->url($p) : null;

        $identity['logoUrl'] = $url($identity['logoPath'] ?? null);
        $identity['watermarkUrl'] = $url($identity['watermarkPath'] ?? null);
        $identity['sealUrls'] = array_map($url, $identity['sealPaths'] ?? []);

        return $identity;
    }
}
