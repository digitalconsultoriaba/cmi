<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\EventConfigService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BannerRequest;
use App\Http\Requests\Admin\UpdateEventRequest;
use App\Http\Resources\Admin\EventResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        // Trilha (spec 008): alterações de configuração são ações sensíveis
        activity('event.updated')
            ->performedOn($event)
            ->withProperties(['reference' => $event->name, 'changed' => array_keys($data)])
            ->log('Configuração do evento "'.$event->name.'" alterada ('
                .implode(', ', array_keys($data)).')');

        return EventResource::make($event->fresh());
    }

    public function publish(Event $event)
    {
        return EventResource::make($this->service->publish($event));
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
