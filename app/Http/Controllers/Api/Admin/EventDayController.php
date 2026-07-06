<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventDay;
use App\Domain\Events\Services\EventDayService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EventDaysRequest;
use App\Http\Resources\Admin\EventDayResource;
use Illuminate\Http\Request;

/**
 * Dias do evento (spec 012): duração/datas, finalização, reabertura, bloqueio.
 */
class EventDayController extends Controller
{
    public function __construct(private readonly EventDayService $service)
    {
    }

    public function index(Event $event)
    {
        return EventDayResource::collection($this->daysFor($event));
    }

    public function upsert(EventDaysRequest $request, Event $event)
    {
        $this->service->upsertDays($event, $request->days(), $request->user());

        return EventDayResource::collection($this->daysFor($event->fresh()));
    }

    /** Dias com contagem + back-link do evento (evita N+1 na situação derivada). */
    private function daysFor(Event $event)
    {
        $days = $event->eventDays()->withCount('checkins')->get();
        $event->setRelation('eventDays', $days);
        $days->each(fn ($d) => $d->setRelation('event', $event));

        return $days;
    }

    public function finalize(Request $request, Event $event, EventDay $day)
    {
        $this->assertBelongs($day, $event);

        return EventDayResource::make($this->service->finalize($day, $request->user()));
    }

    public function reopen(Request $request, Event $event, EventDay $day)
    {
        $this->assertBelongs($day, $event);
        $data = $request->validate(
            ['reason' => ['required', 'string', 'max:500']],
            ['reason.required' => 'Informe a justificativa da reabertura.']
        );

        return EventDayResource::make($this->service->reopen($day, $request->user(), $data['reason']));
    }

    public function block(Request $request, Event $event, EventDay $day)
    {
        $this->assertBelongs($day, $event);

        return EventDayResource::make($this->service->setBlocked($day, true, $request->user()));
    }

    public function unblock(Request $request, Event $event, EventDay $day)
    {
        $this->assertBelongs($day, $event);

        return EventDayResource::make($this->service->setBlocked($day, false, $request->user()));
    }

    private function assertBelongs(EventDay $day, Event $event): void
    {
        abort_unless($day->event_id === $event->id, 404);
    }
}
