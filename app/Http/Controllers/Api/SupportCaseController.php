<?php

namespace App\Http\Controllers\Api;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\SupportCase;
use App\Domain\Events\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportCaseRequest;
use App\Http\Resources\SupportCaseResource;
use Illuminate\Http\Request;

class SupportCaseController extends Controller
{
    public function index(Request $request)
    {
        $cases = SupportCase::query()
            ->where('user_id', $request->user()->id)
            ->with(['order', 'ticket'])
            ->latest('updated_at')
            ->get();

        return SupportCaseResource::collection($cases);
    }

    public function store(SupportCaseRequest $request)
    {
        $user = $request->user();

        $order = $request->validated('order_code')
            ? Order::query()->where('code', $request->validated('order_code'))->first()
            : null;
        $ticket = $request->validated('ticket_code')
            ? Ticket::query()->where('code', $request->validated('ticket_code'))->first()
            : null;

        // Posse: o chamado só pode referenciar pedido/ingresso do próprio usuário
        // (evita IDOR — abrir reembolso/cancelamento sobre recurso de terceiro).
        abort_if($order !== null && $order->buyer_user_id !== $user->id, 403,
            'Este pedido não pertence à sua conta.');
        abort_if($ticket !== null && ! (
            $ticket->participant_user_id === $user->id
            || ($ticket->participant_email !== null
                && mb_strtolower($ticket->participant_email) === mb_strtolower($user->email))
            || $ticket->order?->buyer_user_id === $user->id
        ), 403, 'Este ingresso não pertence à sua conta.');

        $event = $ticket?->event ?? $order?->event
            ?? \App\Domain\Events\Models\Event::query()->latest('id')->firstOrFail();

        $case = SupportCase::query()->create([
            'event_id' => $event->id,
            'order_id' => $order?->id,
            'ticket_id' => $ticket?->id,
            'user_id' => $request->user()->id,
            'type' => $request->validated('type'),
            'status' => 'open',
            'subject' => $request->validated('subject'),
        ]);

        $case->notes()->create([
            'author_user_id' => $request->user()->id,
            'body' => $request->validated('message'),
            'visible_to_attendee' => true,
            'from_attendee' => true,
        ]);

        return SupportCaseResource::make($case->load(['notes.author', 'order', 'ticket']))
            ->response()->setStatusCode(201);
    }

    public function show(Request $request, SupportCase $supportCase)
    {
        $this->authorize('view', $supportCase);

        return SupportCaseResource::make($supportCase->load(['notes.author', 'order', 'ticket']));
    }

    public function addNote(Request $request, SupportCase $supportCase)
    {
        $this->authorize('view', $supportCase);

        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        $supportCase->notes()->create([
            'author_user_id' => $request->user()->id,
            'body' => $data['message'],
            'visible_to_attendee' => true,
            'from_attendee' => true,
        ]);

        // Nova mensagem em caso finalizado reabre a conversa
        if ($supportCase->status === 'finished') {
            $supportCase->forceFill(['status' => 'reopened'])->save();
        }

        return SupportCaseResource::make($supportCase->fresh()->load(['notes.author', 'order', 'ticket']));
    }
}
