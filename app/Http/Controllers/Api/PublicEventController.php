<?php

namespace App\Http\Controllers\Api;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicEventResource;
use App\Support\ApiResponse;

class PublicEventController extends Controller
{
    public function show(Event $event)
    {
        $status = $event->status?->slug;

        // Rascunho não existe para o público (FR-002)
        abort_if($status === EventStatus::DRAFT, 404);

        // Oculto do site (interruptor "Mostrar no site") → não aparece/inscreve.
        abort_if(! $event->visible_on_site, 404);

        if ($status === EventStatus::CANCELLED) {
            return ApiResponse::data([
                'name' => $event->name,
                'slug' => $event->slug,
                'cancelled' => true,
                'cancelReason' => $event->cancel_reason,
            ]);
        }

        return PublicEventResource::make($event);
    }
}
