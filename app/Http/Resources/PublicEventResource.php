<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class PublicEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'bannerUrl' => $this->banner_path ? Storage::disk('public')->url($this->banner_path) : null,
            'startsAt' => $this->starts_at?->toISOString(),
            'endsAt' => $this->ends_at?->toISOString(),
            'location' => $this->location,
            'locationMapUrl' => $this->location_map_url,
            'salesState' => $this->salesState(),
            'salesStartAt' => $this->sales_start_at?->toISOString(),
            'salesEndAt' => $this->sales_end_at?->toISOString(),
            'allowShirtChoice' => $this->allow_shirt_choice,
            'requiresShirt' => $this->requires_shirt,
            'allowCourtesy' => $this->allow_courtesy,
            'blocks' => $this->landingBlocks()
                ->where('is_active', true)->orderBy('sort')->orderBy('id')->get()
                ->map(fn ($block) => [
                    'type' => $block->type,
                    'sort' => $block->sort,
                    'payload' => $block->payload,
                ])->values(),
            'ticketTypes' => $this->ticketTypes()
                ->where('is_active', true)->orderBy('sort')->orderBy('id')->get()
                ->map(function ($type) {
                    $lot = $this->resource->currentLot($type);

                    return [
                        'id' => $type->id,
                        'name' => $type->name,
                        'isCouple' => $type->is_couple,
                        'includesShirt' => $type->includes_shirt,
                        'isCourtesy' => $type->is_courtesy,
                        'audience' => $type->audience,
                        'effectivePrice' => $lot?->effectivePrice($type) ?? $type->price,
                        'currentLotName' => $lot?->name,
                        'purchasable' => $lot !== null && ! $type->soldOut() && ! $type->is_courtesy,
                        'soldOut' => $type->soldOut(),
                        'available' => $type->available(),
                    ];
                })->values(),
            'shirtModels' => $this->shirtModels()
                ->where('is_active', true)->with('sizes')->orderBy('sort')->get()
                ->map(fn ($model) => [
                    'id' => $model->id,
                    'label' => $model->label,
                    'sizes' => $model->sizes->where('is_active', true)->values()
                        ->map(fn ($size) => [
                            'id' => $size->id,
                            'label' => $size->label,
                            'soldOut' => $size->soldOut(),
                        ]),
                ])->values(),
        ];
    }

    private function salesState(): string
    {
        $event = $this->resource;

        if ($event->salesOpen()) {
            return 'open';
        }

        if ($event->sales_start_at !== null && Carbon::now()->lt($event->sales_start_at)) {
            return 'soon';
        }

        return $event->soldOut() ? 'soldOut' : 'closed';
    }
}
