<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventSiteItem;
use App\Domain\Events\Models\EventSiteSection;
use App\Domain\Events\Services\SiteItemService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSiteItemRequest;
use App\Http\Requests\Admin\UpdateSiteItemRequest;
use App\Http\Resources\Admin\SiteItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class SiteItemController extends Controller
{
    public function __construct(private SiteItemService $service) {}

    public function index(Event $event, EventSiteSection $section)
    {
        $this->assertSection($event, $section);

        return SiteItemResource::collection(
            $section->items()->with('children.children')->get()
        );
    }

    public function store(StoreSiteItemRequest $request, Event $event, EventSiteSection $section)
    {
        $this->assertSection($event, $section);

        $item = $this->service->create($section, $request->validated());

        return SiteItemResource::make($item->load('children'))->response()->setStatusCode(201);
    }

    public function update(UpdateSiteItemRequest $request, Event $event, EventSiteSection $section, EventSiteItem $item)
    {
        $this->assertItem($event, $section, $item);

        $updated = $this->service->update($item, $request->validated());

        return SiteItemResource::make($updated->load('children'));
    }

    public function destroy(Event $event, EventSiteSection $section, EventSiteItem $item)
    {
        $this->assertItem($event, $section, $item);

        $this->service->delete($item);

        return ApiResponse::data(['deleted' => true]);
    }

    public function reorder(Request $request, Event $event, EventSiteSection $section)
    {
        $this->assertSection($event, $section);

        $data = $request->validate([
            'parentItemId' => ['sometimes', 'nullable', 'integer'],
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);

        $this->service->reorder($section, $data['parentItemId'] ?? null, $data['order']);

        return SiteItemResource::collection(
            $section->items()->with('children.children')->get()
        );
    }

    private function assertSection(Event $event, EventSiteSection $section): void
    {
        abort_unless($section->site?->event_id === $event->id, 404);
    }

    private function assertItem(Event $event, EventSiteSection $section, EventSiteItem $item): void
    {
        $this->assertSection($event, $section);
        abort_unless($item->event_site_section_id === $section->id, 404);
    }
}
