<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventStatus;
use Illuminate\Support\Facades\DB;

class EventConfigService
{
    /** Alterações só em evento não terminal (cancelled/finished → 409). */
    public function ensureEditable(Event $event): void
    {
        if (in_array($event->status?->slug, EventStatus::TERMINAL, true)) {
            throw new DomainRuleViolation(
                'Evento cancelado ou encerrado não pode ser alterado.',
                'terminal_status'
            );
        }
    }

    /**
     * Publicar exige dados mínimos (FR-004); 409 lista o que falta.
     */
    public function publish(Event $event): Event
    {
        $this->ensureEditable($event);

        if ($event->status?->slug === EventStatus::PUBLISHED) {
            throw new DomainRuleViolation('O evento já está publicado.', 'already_published');
        }

        $missing = [];

        if (blank($event->name)) {
            $missing[] = 'nome';
        }
        if ($event->starts_at === null) {
            $missing[] = 'data de início';
        }
        if ($event->event_type_id === null) {
            $missing[] = 'tipo de evento';
        }
        if (! $event->ticketTypes()->where('is_active', true)->exists()) {
            $missing[] = 'ao menos um tipo de ingresso ativo';
        }

        if ($missing !== []) {
            throw new DomainRuleViolation(
                'O evento ainda não pode ser publicado.',
                'publish_requirements',
                ['missing' => $missing]
            );
        }

        return DB::transaction(function () use ($event) {
            return $event->transitionTo(EventStatus::PUBLISHED);
        });
    }

    public function cancel(Event $event, string $reason): Event
    {
        $this->ensureEditable($event);

        $event = DB::transaction(function () use ($event, $reason) {
            $event->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancel_reason' => $reason,
            ]);

            return $event->transitionTo(EventStatus::CANCELLED);
        });

        // Cascata resiliente do pós-venda (spec 006): pedidos vivos cancelados,
        // fila de devoluções 100% e avisos — falha em um pedido não interrompe.
        if (auth()->user() !== null) {
            app(CancelEventCascade::class)->run($event, auth()->user());
        }

        return $event;
    }
}
