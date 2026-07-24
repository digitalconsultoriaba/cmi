<?php

namespace App\Http\Controllers\Api\Public;

use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketStatus;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Autenticação pública do ingresso (spec 014): dado o código do QR, confirma se
 * o ingresso foi realmente emitido pelo sistema — SEM fazer check-in (isso é
 * exclusivo da portaria autenticada). Só expõe dados que já constam no ingresso.
 */
class PublicTicketController extends Controller
{
    /** Status que representam um ingresso legitimamente emitido. */
    private const VALID = [
        TicketStatus::PAID,
        TicketStatus::CONFIRMED,
        TicketStatus::COURTESY,
        TicketStatus::USED,
    ];

    public function verify(string $code): JsonResponse
    {
        $code = strtoupper(trim($code));

        $ticket = Ticket::query()
            ->with(['event', 'ticketType', 'ticketLot', 'status'])
            ->where('code', $code)
            ->first();

        if ($ticket === null) {
            return ApiResponse::data(['valid' => false, 'code' => $code]);
        }

        $slug = $ticket->status?->slug;

        return ApiResponse::data([
            'valid' => in_array($slug, self::VALID, true),
            'used' => $slug === TicketStatus::USED,
            'code' => $ticket->code,
            'status' => $slug,
            'statusLabel' => $ticket->status?->name,
            'participantName' => $ticket->participant_name,
            'eventName' => $ticket->event?->name,
            'ticketType' => $ticket->is_courtesy ? 'Cortesia' : $ticket->ticketType?->name,
            'lote' => $ticket->ticketLot?->name,
            'value' => $ticket->is_courtesy ? 'Cortesia' : 'R$ '.number_format((float) $ticket->unit_price, 2, ',', '.'),
        ]);
    }
}
