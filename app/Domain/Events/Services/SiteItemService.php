<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\EventSiteItem;
use App\Domain\Events\Models\EventSiteSection;
use App\Domain\Events\Services\Translation\TranslationService;
use Illuminate\Support\Facades\DB;

/**
 * CRUD e reordenação de itens das seções dinâmicas do Site (spec 013),
 * com um nível de aninhamento. Escritas em transação; payload traduzido.
 */
class SiteItemService
{
    public function __construct(private TranslationService $translations) {}

    public function create(EventSiteSection $section, array $data): EventSiteItem
    {
        return DB::transaction(function () use ($section, $data) {
            $parentId = $this->resolveParent($section, $data['parentItemId'] ?? null);
            $active = $section->site->activeLanguages();

            $sort = $data['sort'] ?? ($section->allItems()
                ->where('parent_item_id', $parentId)->max('sort') + 1);

            return $section->allItems()->create([
                'parent_item_id' => $parentId,
                'sort' => $sort,
                'payload' => $this->translations->fillPayload((array) ($data['payload'] ?? []), $active),
            ]);
        });
    }

    public function update(EventSiteItem $item, array $data): EventSiteItem
    {
        return DB::transaction(function () use ($item, $data) {
            $active = $item->section->site->activeLanguages();
            $attrs = [];

            if (array_key_exists('payload', $data)) {
                $attrs['payload'] = $this->translations->fillPayload((array) $data['payload'], $active);
            }
            if (array_key_exists('parentItemId', $data)) {
                $attrs['parent_item_id'] = $this->resolveParent($item->section, $data['parentItemId']);
            }
            if (array_key_exists('sort', $data)) {
                $attrs['sort'] = (int) $data['sort'];
            }

            $item->update($attrs);

            return $item->fresh('children');
        });
    }

    public function delete(EventSiteItem $item): void
    {
        DB::transaction(function () use ($item) {
            $item->children()->get()->each->delete(); // soft delete dos filhos
            $item->delete();
        });
    }

    /** @param  int[]  $order  ids na nova ordem (dentro de um escopo pai/topo) */
    public function reorder(EventSiteSection $section, ?int $parentId, array $order): void
    {
        $parentId = $parentId !== null ? $this->resolveParent($section, $parentId) : null;

        $valid = $section->allItems()
            ->where('parent_item_id', $parentId)
            ->whereIn('id', $order)->pluck('id')->all();

        if (count($valid) !== count($order)) {
            throw new DomainRuleViolation('Lista de ordenação contém itens de outra seção.', 'invalid_reorder');
        }

        DB::transaction(function () use ($section, $order) {
            foreach ($order as $index => $id) {
                $section->allItems()->where('id', $id)->update(['sort' => $index]);
            }
        });
    }

    /** Valida que o pai (se houver) pertence à mesma seção. */
    private function resolveParent(EventSiteSection $section, ?int $parentId): ?int
    {
        if ($parentId === null) {
            return null;
        }

        $exists = $section->allItems()->whereKey($parentId)->exists();
        if (! $exists) {
            throw new DomainRuleViolation('Item pai não pertence a esta seção.', 'invalid_parent');
        }

        return $parentId;
    }
}
