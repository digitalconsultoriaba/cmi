<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\TicketLot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TicketLotRequest;
use App\Http\Resources\Admin\TicketLotResource;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketLotController extends Controller
{
    public function index(Event $event)
    {
        return TicketLotResource::collection(
            $event->ticketLots()->with('ticketType')->orderBy('sort')->orderBy('id')->get()
        );
    }

    public function store(TicketLotRequest $request, Event $event)
    {
        $lot = $event->ticketLots()->create($request->validated());

        return TicketLotResource::make($lot)->response()->setStatusCode(201);
    }

    public function update(TicketLotRequest $request, Event $event, TicketLot $ticketLot)
    {
        $ticketLot->update($request->validated());

        return TicketLotResource::make($ticketLot->fresh());
    }

    public function destroy(Event $event, TicketLot $ticketLot)
    {
        if ($ticketLot->hasSales()) {
            throw new DomainRuleViolation(
                'Lote com vendas não pode ser excluído — desative-o.',
                'has_sales'
            );
        }

        $ticketLot->delete();

        return ApiResponse::data(null);
    }

    public function reorder(Request $request, Event $event)
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $valid = $event->ticketLots()->whereIn('id', $ids)->pluck('id')->all();

        if (count($valid) !== count($ids)) {
            throw new DomainRuleViolation('Lista de ordenação contém itens de outro evento.', 'invalid_reorder');
        }

        DB::transaction(function () use ($ids, $event) {
            foreach ($ids as $index => $id) {
                $event->ticketLots()->where('id', $id)->update(['sort' => $index]);
            }
        });

        return $this->index($event);
    }
}
