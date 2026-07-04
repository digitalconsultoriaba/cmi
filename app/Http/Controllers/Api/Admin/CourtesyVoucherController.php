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
}
