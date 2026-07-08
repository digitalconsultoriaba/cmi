<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParticipantCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'sort' => $this->sort,
            'isActive' => $this->is_active,
            'fields' => ParticipantFieldResource::collection($this->whenLoaded('fields')),
        ];
    }
}
