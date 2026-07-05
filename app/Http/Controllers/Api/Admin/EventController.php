<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\EventConfigService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BannerRequest;
use App\Http\Requests\Admin\StoreEventRequest;
use App\Http\Requests\Admin\UpdateEventRequest;
use App\Http\Resources\Admin\EventResource;
use App\Domain\Events\Models\EventStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventController extends Controller
{
    public function __construct(private readonly EventConfigService $service)
    {
    }

    public function index()
    {
        return EventResource::collection(Event::query()->orderBy('id')->get());
    }

    public function show(Event $event)
    {
        return EventResource::make($event);
    }

    /** Cria um evento em RASCUNHO (spec 009 — "Novo evento"). */
    public function store(StoreEventRequest $request)
    {
        $data = $request->validated();

        // slug único derivado do nome
        $base = Str::slug($data['name']) ?: 'evento';
        $slug = $base;
        $i = 1;
        while (Event::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        $event = Event::query()->create([
            ...$data,
            'slug' => $slug,
            'status_id' => EventStatus::idFor(EventStatus::DRAFT),
        ]);

        return EventResource::make($event->fresh())->response()->setStatusCode(201);
    }

    public function update(UpdateEventRequest $request, Event $event)
    {
        $this->service->ensureEditable($event);

        $data = $request->validated();

        // Capacidade nunca abaixo do vendido (data-model, regra 4)
        if (array_key_exists('total_capacity', $data) && $data['total_capacity'] !== null
            && $data['total_capacity'] < $event->ticketsSold()) {
            throw new DomainRuleViolation(
                'A capacidade não pode ficar abaixo do total já vendido ('.$event->ticketsSold().').',
                'capacity_below_sold'
            );
        }

        $event->update($data);

        // Trilha (spec 008): alterações de configuração são ações sensíveis.
        // A lista de campos vai nas propriedades (detalhe), não na descrição.
        activity('event.updated')
            ->performedOn($event)
            ->withProperties(['reference' => $event->name, 'changed' => array_keys($data)])
            ->log('Configuração do evento "'.$event->name.'" alterada ('
                .count($data).' campo(s))');

        return EventResource::make($event->fresh());
    }

    public function publish(Event $event)
    {
        return EventResource::make($this->service->publish($event));
    }

    /** Interruptor "Mostrar no site" — visibilidade pública (independe do status). */
    public function visibility(Request $request, Event $event)
    {
        $data = $request->validate(['visible' => ['required', 'boolean']]);

        $event->update(['visible_on_site' => $data['visible']]);

        activity('event.updated')
            ->performedOn($event)
            ->withProperties(['reference' => $event->name, 'visibleOnSite' => $data['visible']])
            ->log('Evento "'.$event->name.'" '.($data['visible'] ? 'exibido no' : 'ocultado do').' site');

        return EventResource::make($event->fresh());
    }

    public function cancel(Request $request, Event $event)
    {
        $request->validate(
            ['reason' => ['required', 'string', 'max:500']],
            [], ['reason' => 'motivo']
        );

        return EventResource::make($this->service->cancel($event, $request->input('reason')));
    }

    public function banner(BannerRequest $request, Event $event)
    {
        $this->service->ensureEditable($event);

        $old = $event->banner_path;
        $path = $request->file('banner')->store('banners', 'public');
        $event->update(['banner_path' => $path]);

        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        return EventResource::make($event->fresh());
    }
}
