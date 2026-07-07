<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\EventSiteService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateEventSiteRequest;
use App\Http\Resources\Admin\EventSiteResource;

class EventSiteController extends Controller
{
    public function __construct(private EventSiteService $service) {}

    public function show(Event $event)
    {
        $site = $this->service->ensureSite($event);

        // fresh() evita o 201 automático do JsonResource em model recém-criado.
        return EventSiteResource::make($site->fresh('sections.allItems'));
    }

    public function update(UpdateEventSiteRequest $request, Event $event)
    {
        $site = $this->service->ensureSite($event);
        $updated = $this->service->updateConfig($site, $request->validated());

        return EventSiteResource::make($updated->fresh('sections.allItems'));
    }

    public function publish(Event $event)
    {
        $site = $this->service->ensureSite($event);

        return EventSiteResource::make(
            $this->service->publish($site)->load('sections.allItems')
        );
    }

    public function unpublish(Event $event)
    {
        $site = $this->service->ensureSite($event);

        return EventSiteResource::make(
            $this->service->unpublish($site)->load('sections.allItems')
        );
    }
}
