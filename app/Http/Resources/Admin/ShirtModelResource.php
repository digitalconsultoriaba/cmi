<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShirtModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'isActive' => $this->is_active,
            'sort' => $this->sort,
            'sizes' => $this->sizes->map(fn ($size) => [
                'id' => $size->id,
                'label' => $size->label,
                'stockQuantity' => $size->stock_quantity,
                'soldCount' => $size->sold_count,
                // Disponível derivado: null = ilimitado (spec 009, FR-010)
                'available' => $size->stock_quantity === null
                    ? null
                    : max(0, $size->stock_quantity - $size->sold_count),
                'isActive' => $size->is_active,
                'sort' => $size->sort,
                'soldOut' => $size->soldOut(),
            ])->values(),
        ];
    }
}
