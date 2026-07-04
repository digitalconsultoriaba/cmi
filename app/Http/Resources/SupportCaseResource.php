<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Notas filtradas por audiência: o inscrito NUNCA recebe notas internas
 * (crie com ::forAttendee() ou ::forStaff()).
 */
class SupportCaseResource extends JsonResource
{
    private bool $staffView = false;

    public static function forStaff($resource): self
    {
        $instance = new self($resource);
        $instance->staffView = true;

        return $instance;
    }

    public function toArray(Request $request): array
    {
        $notes = $this->whenLoaded('notes', function () {
            $notes = $this->notes;

            if (! $this->staffView) {
                $notes = $notes->where('visible_to_attendee', true)->values();
            }

            return $notes->map(fn ($note) => [
                'id' => $note->id,
                'body' => $note->body,
                'fromAttendee' => (bool) $note->from_attendee,
                'visibleToAttendee' => (bool) $note->visible_to_attendee,
                'author' => $note->author?->name,
                'createdAt' => $note->created_at?->toISOString(),
            ]);
        });

        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'subject' => $this->subject,
            'refundAmount' => $this->refund_amount,
            'orderCode' => $this->order?->code,
            'ticketCode' => $this->ticket?->code,
            'requester' => $this->when($this->staffView, fn () => $this->user?->name),
            'paymentMethod' => $this->when($this->staffView, fn () => $this->order?->payments()
                ->whereNotNull('paid_at')->latest('paid_at')->first()?->method),
            'updatedAt' => $this->updated_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'notes' => $notes,
        ];
    }
}
