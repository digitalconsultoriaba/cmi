<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parentItemId' => $this->parent_item_id,
            'sort' => $this->sort,
            'payload' => $this->payload ?? [],
            'children' => SiteItemResource::collection(
                $this->whenLoaded('children')
            ),
        ];
    }
}
