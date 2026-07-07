<?php

namespace App\Http\Resources;

use App\Domain\Events\Models\TicketStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'status' => $this->status?->slug,
            'participantName' => $this->participant_name,
            'participantEmail' => $this->participant_email,
            'ticketTypeName' => $this->ticketType?->name,
            'isCourtesy' => (bool) $this->is_courtesy,
            'unitPrice' => $this->unit_price,
            'participantCategoryKey' => $this->participant_category_key,
            'participantFields' => $this->participant_fields,
            'shirt' => $this->shirt_size_id ? [
                'model' => $this->shirtModel?->label,
                'size' => $this->shirtSize?->label,
            ] : null,
            'companion' => $this->companion_name ? [
                'name' => $this->companion_name,
                'shirtModel' => $this->companionShirtModel?->label,
                'shirtSize' => $this->companionShirtSize?->label,
            ] : null,
            'event' => [
                'name' => $this->event?->name,
                'slug' => $this->event?->slug,
                'startsAt' => $this->event?->starts_at?->toISOString(),
            ],
            'orderCode' => $this->order?->code,
            'receiptAvailable' => in_array(
                $this->status?->slug,
                [TicketStatus::PAID, TicketStatus::CONFIRMED, TicketStatus::COURTESY, TicketStatus::USED],
                true
            ),
            // Elegibilidade derivada no servidor (spec 006) — o front nunca
            // reimplementa a política.
            'cancellable' => $this->isCancellable(),
            'transferable' => $this->isTransferable(),
            'refundPreview' => $this->refundPreview(),
            'transferredFromCode' => $this->transferredFrom?->code,
            'transferredToCode' => $this->transferredTo?->code,
        ];
    }

    private function isCancellable(): bool
    {
        return (bool) $this->event?->allow_user_cancel
            && in_array($this->status?->slug, TicketStatus::LIVE, true);
    }

    private function isTransferable(): bool
    {
        return (bool) $this->event?->allow_transfer
            && in_array($this->status?->slug, [TicketStatus::PAID, TicketStatus::CONFIRMED], true)
            && ($this->event?->starts_at === null || now()->lt($this->event->starts_at))
            && ! \App\Domain\Events\Models\CourtesyVoucher::query()
                ->where('redeemed_ticket_id', $this->id)->exists();
    }

    private function refundPreview(): ?string
    {
        $orderStatus = $this->order?->status?->slug;

        if ($this->is_courtesy
            || ! in_array($orderStatus, ['paid', 'partially_paid'], true)
            || ! $this->isCancellable()) {
            return null;
        }

        return app(\App\Domain\Events\Services\RefundPolicy::class)
            ->refundableAmount($this->resource);
    }
}
