<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\TicketType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TicketTypeRequest;
use App\Http\Resources\Admin\TicketTypeResource;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketTypeController extends Controller
{
    public function index(Event $event)
    {
        return TicketTypeResource::collection(
            $event->ticketTypes()->orderBy('sort')->orderBy('id')->get()
        );
    }

    public function store(TicketTypeRequest $request, Event $event)
    {
        $type = $event->ticketTypes()->create($request->validated());

        return TicketTypeResource::make($type)->response()->setStatusCode(201);
    }

    public function update(TicketTypeRequest $request, Event $event, TicketType $ticketType)
    {
        $data = $request->validated();

        if (array_key_exists('capacity', $data) && $data['capacity'] !== null
            && $data['capacity'] < $ticketType->soldCount()) {
            throw new DomainRuleViolation(
                'A capacidade não pode ficar abaixo do já vendido ('.$ticketType->soldCount().').',
                'capacity_below_sold'
            );
        }

        $ticketType->update($data);

        return TicketTypeResource::make($ticketType->fresh());
    }

    public function destroy(Event $event, TicketType $ticketType)
    {
        if ($ticketType->hasSales()) {
            throw new DomainRuleViolation(
                'Tipo de ingresso com vendas não pode ser excluído — desative-o.',
                'has_sales'
            );
        }

        $ticketType->delete();

        return ApiResponse::data(null);
    }

    public function reorder(Request $request, Event $event)
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $valid = $event->ticketTypes()->whereIn('id', $ids)->pluck('id')->all();

        if (count($valid) !== count($ids)) {
            throw new DomainRuleViolation('Lista de ordenação contém itens de outro evento.', 'invalid_reorder');
        }

        DB::transaction(function () use ($ids, $event) {
            foreach ($ids as $index => $id) {
                $event->ticketTypes()->where('id', $id)->update(['sort' => $index]);
            }
        });

        return $this->index($event);
    }
}
