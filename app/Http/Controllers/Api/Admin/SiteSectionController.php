<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventSiteSection;
use App\Domain\Events\Services\EventSiteService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSectionRequest;
use App\Http\Resources\Admin\SiteSectionResource;
use Illuminate\Http\Request;

class SiteSectionController extends Controller
{
    public function __construct(private EventSiteService $service) {}

    public function update(UpdateSiteSectionRequest $request, Event $event, EventSiteSection $section)
    {
        $this->assertOwnership($event, $section);

        $updated = $this->service->upsertSection($section, $request->validated());

        return SiteSectionResource::make($updated->load('allItems'));
    }

    public function reorder(Request $request, Event $event)
    {
        $order = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ])['order'];

        $site = $this->service->ensureSite($event);
        $this->service->reorderSections($site, $order);

        return SiteSectionResource::collection(
            $site->fresh('sections.allItems')->sections
        );
    }

    private function assertOwnership(Event $event, EventSiteSection $section): void
    {
        abort_unless($section->site?->event_id === $event->id, 404);
    }
}
