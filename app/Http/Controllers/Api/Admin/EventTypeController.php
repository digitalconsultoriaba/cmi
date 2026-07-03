<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EventTypeRequest;
use App\Support\ApiResponse;

class EventTypeController extends Controller
{
    public function index()
    {
        $types = EventType::query()->orderBy('name')->get()->map(fn ($type) => [
            'id' => $type->id,
            'name' => $type->name,
            'isActive' => $type->is_active,
            'eventsCount' => Event::query()->where('event_type_id', $type->id)->count(),
        ]);

        return ApiResponse::data($types);
    }

    public function store(EventTypeRequest $request)
    {
        $type = EventType::query()->create($request->validated());

        return ApiResponse::data([
            'id' => $type->id, 'name' => $type->name, 'isActive' => $type->is_active,
        ], 201);
    }

    public function update(EventTypeRequest $request, EventType $eventType)
    {
        $eventType->update($request->validated());

        return ApiResponse::data([
            'id' => $eventType->id, 'name' => $eventType->name, 'isActive' => $eventType->is_active,
        ]);
    }

    public function destroy(EventType $eventType)
    {
        if (Event::query()->where('event_type_id', $eventType->id)->exists()) {
            throw new DomainRuleViolation(
                'Tipo de evento em uso não pode ser excluído — desative-o.',
                'in_use'
            );
        }

        $eventType->delete();

        return ApiResponse::data(null);
    }
}
