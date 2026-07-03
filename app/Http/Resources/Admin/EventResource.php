<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'eventTypeId' => $this->event_type_id,
            'eventTypeName' => $this->eventType?->name,
            'startsAt' => $this->starts_at?->toISOString(),
            'endsAt' => $this->ends_at?->toISOString(),
            'location' => $this->location,
            'locationMapUrl' => $this->location_map_url,
            'bannerUrl' => $this->banner_path ? Storage::disk('public')->url($this->banner_path) : null,
            'totalCapacity' => $this->total_capacity,
            'salesStartAt' => $this->sales_start_at?->toISOString(),
            'salesEndAt' => $this->sales_end_at?->toISOString(),
            'reservationTtlMinutes' => $this->reservation_ttl_minutes,
            'participationRules' => $this->participation_rules,
            'internalNotes' => $this->internal_notes,
            'pricingMode' => $this->pricing_mode,
            'allowCard' => $this->allow_card,
            'allowBoleto' => $this->allow_boleto,
            'allowPix' => $this->allow_pix,
            'allowShirtChoice' => $this->allow_shirt_choice,
            'requiresShirt' => $this->requires_shirt,
            'allowKit' => $this->allow_kit,
            'allowTransfer' => $this->allow_transfer,
            'allowUserCancel' => $this->allow_user_cancel,
            'allowRefundRequest' => $this->allow_refund_request,
            'allowCourtesy' => $this->allow_courtesy,
            'courtesyPaidThreshold' => $this->courtesy_paid_threshold,
            'courtesyGrantPerThreshold' => $this->courtesy_grant_per_threshold,
            'courtesyLimitPerAccount' => $this->courtesy_limit_per_account,
            'status' => $this->status?->slug,
            'cancelledAt' => $this->cancelled_at?->toISOString(),
            'cancelReason' => $this->cancel_reason,
            // Derivações da fundação — nunca recalculadas no front
            'salesOpen' => $this->salesOpen(),
            'available' => $this->available(),
            'soldOut' => $this->soldOut(),
            'ticketsSold' => $this->ticketsSold(),
        ];
    }
}
