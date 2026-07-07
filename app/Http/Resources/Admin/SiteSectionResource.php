<?php

namespace App\Http\Resources\Admin;

use App\Domain\Events\Models\EventSiteItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'sort' => $this->sort,
            'isActive' => $this->is_active,
            'isDynamic' => $this->isDynamic(),
            'payload' => $this->payload ?? [],
            'items' => $this->when($this->isDynamic(), fn () => $this->tree()),
        ];
    }

    /** Itens de topo com filhos aninhados (a partir de allItems já carregado). */
    private function tree(): array
    {
        $all = $this->relationLoaded('allItems')
            ? $this->allItems
            : $this->allItems()->get();

        $byParent = $all->groupBy('parent_item_id');

        $build = function ($parentId) use (&$build, $byParent) {
            return ($byParent[$parentId] ?? collect())
                ->sortBy([['sort', 'asc'], ['id', 'asc']])
                ->map(fn (EventSiteItem $item) => [
                    'id' => $item->id,
                    'parentItemId' => $item->parent_item_id,
                    'sort' => $item->sort,
                    'payload' => $item->payload ?? [],
                    'children' => $build($item->id),
                ])->values()->all();
        };

        return $build(null);
    }
}
