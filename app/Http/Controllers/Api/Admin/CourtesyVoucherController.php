<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\CourtesyVoucher;
use App\Domain\Events\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateVouchersRequest;
use App\Http\Resources\Admin\CourtesyVoucherResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourtesyVoucherController extends Controller
{
    public function index(Request $request, Event $event)
    {
        $query = $event->courtesyVouchers()->with('ticketType')->latest('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return CourtesyVoucherResource::collection($query->get());
    }

    /**
     * Relatório de cortesias do evento pelo ciclo do voucher:
     * geradas (todas) / distribuídas (entregues a alguém) / resgatadas (viraram ingresso).
     * Inclui também as cortesias automáticas (regra do evento) que não vêm de voucher.
     * Cada linha traz os dados do titular quando o voucher já foi resgatado.
     */
    public function stats(Request $request, Event $event)
    {
        $vouchers = $event->courtesyVouchers()
            ->with(['redeemedTicket.order.buyerUser'])
            ->latest('id')
            ->get();

        $voucherTicketIds = $vouchers->pluck('redeemed_ticket_id')->filter()->values()->all();

        // Cortesias automáticas: ingressos de cortesia que não nasceram de um voucher.
        $autoTickets = $event->tickets()
            ->where('is_courtesy', true)
            ->when($voucherTicketIds, fn ($q) => $q->whereNotIn('id', $voucherTicketIds))
            ->with(['order.buyerUser'])
            ->get();

        $fromTicket = fn ($t, array $extra = []) => array_merge([
            'name' => $t?->participant_name,
            'potencia' => $t?->order?->buyerUser?->potencia,
            'loja' => $t?->order?->buyerUser?->loja,
            'cargoLoja' => $t?->order?->buyerUser?->cargo_loja,
            'cargoPotencia' => $t?->order?->buyerUser?->cargo_potencia,
        ], $extra);

        $fromVoucher = function ($v) use ($fromTicket) {
            $t = $v->redeemedTicket;

            return $fromTicket($t, [
                'code' => $v->code,
                'note' => $v->note,
                'status' => $v->status,
                'name' => $t?->participant_name ?: ($v->note ?: null),
                'distributedAt' => $v->distributed_at?->toDateString(),
            ]);
        };

        $auto = $autoTickets->map(fn ($t) => $fromTicket($t, [
            'code' => null,
            'note' => 'Cortesia automática',
            'status' => CourtesyVoucher::REDEEMED,
        ]));

        $geradas = $vouchers->map($fromVoucher)->concat($auto)->values();
        $distribuidas = $vouchers
            ->filter(fn ($v) => in_array($v->status, [CourtesyVoucher::DISTRIBUTED, CourtesyVoucher::REDEEMED], true))
            ->map($fromVoucher)->concat($auto)->values();
        $resgatadas = $vouchers
            ->filter(fn ($v) => $v->status === CourtesyVoucher::REDEEMED)
            ->map($fromVoucher)->concat($auto)->values();

        return response()->json([
            'data' => [
                'counts' => [
                    'geradas' => $geradas->count(),
                    'distribuidas' => $distribuidas->count(),
                    'resgatadas' => $resgatadas->count(),
                ],
                'people' => [
                    'geradas' => $geradas,
                    'distribuidas' => $distribuidas,
                    'resgatadas' => $resgatadas,
                ],
            ],
        ]);
    }

    public function generate(GenerateVouchersRequest $request, Event $event)
    {
        $vouchers = DB::transaction(function () use ($request, $event) {
            $batch = collect(range(1, (int) $request->validated('quantity')))
                ->map(fn () => $event->courtesyVouchers()->create([
                    'ticket_type_id' => $request->validated('ticket_type_id'),
                ]));

            // Trilha (spec 008): 1 ação de emissão = 1 registro (o lote inteiro)
            activity('courtesy.issued')
                ->performedOn($event)
                ->withProperties([
                    'reference' => $event->name,
                    'quantity' => $batch->count(),
                    'codes' => $batch->pluck('code')->all(),
                ])
                ->log($batch->count().' voucher(s) de cortesia emitido(s) para "'.$event->name.'"');

            return $batch;
        });

        return CourtesyVoucherResource::collection($vouchers)
            ->response()->setStatusCode(201);
    }

    public function distribute(Request $request, Event $event, CourtesyVoucher $courtesyVoucher)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $courtesyVoucher->forceFill([
            'distributed_at' => now(),
            'distributed_by' => auth()->id(),
            'note' => $data['note'] ?? $courtesyVoucher->note,
        ]);

        // Ciclo só avança (guarda da fundação) — retroceder/repetir → 409
        $courtesyVoucher->transitionTo(CourtesyVoucher::DISTRIBUTED);

        return CourtesyVoucherResource::make($courtesyVoucher->fresh());
    }

    /**
     * Edita apenas a anotação do voucher (destinatário/observação) após a distribuição,
     * sem alterar a situação. Vale para vouchers distribuídos ou resgatados.
     */
    public function updateNote(Request $request, Event $event, CourtesyVoucher $courtesyVoucher)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $courtesyVoucher->update(['note' => $data['note'] ?? null]);

        return CourtesyVoucherResource::make($courtesyVoucher->fresh());
    }
}
