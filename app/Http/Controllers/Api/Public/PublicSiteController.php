<?php

namespace App\Http\Controllers\Api\Public;

use App\Domain\Events\Models\EventSite;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PublicSiteResource;
use Illuminate\Http\Request;

class PublicSiteController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $site = EventSite::query()->where('slug', $slug)
            ->with(['event.status', 'sections.allItems'])->first();

        // Não vaza rascunho/oculto/inexistente — sempre 404.
        abort_if($site === null || ! $site->isPubliclyVisible(), 404);

        $lang = $this->resolveLang($request, $site);

        return new PublicSiteResource($site, $lang);
    }

    private function resolveLang(Request $request, EventSite $site): string
    {
        $base = (string) config('site.base_locale', 'pt');
        $requested = (string) $request->query('lang', $base);
        $active = $site->activeLanguages();

        return in_array($requested, $active, true) ? $requested : $base;
    }
}
