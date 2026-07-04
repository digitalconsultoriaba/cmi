<?php

namespace App\Http\Controllers\Api\Finance;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\FinancialCategory;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $q = FinancialCategory::query()->orderBy('direction')->orderBy('sort')->orderBy('name');
        if ($d = $request->query('direction')) {
            $q->where('direction', $d);
        }

        return ApiResponse::data($q->get()->map(fn ($c) => $this->present($c))->all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'direction' => ['required', 'in:income,expense'],
            'name' => ['required', 'string', 'max:120'],
        ]);
        $c = FinancialCategory::query()->create($data);

        return ApiResponse::data($this->present($c), 201);
    }

    public function update(Request $request, FinancialCategory $category)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $category->update($data);

        return ApiResponse::data($this->present($category->fresh()));
    }

    public function destroy(FinancialCategory $category)
    {
        if ($category->entries()->exists()) {
            throw new DomainRuleViolation('Categoria com lançamentos não pode ser excluída — inative-a.', 'has_entries');
        }
        $category->delete();

        return ApiResponse::data(null);
    }

    private function present(FinancialCategory $c): array
    {
        return ['id' => $c->id, 'direction' => $c->direction, 'name' => $c->name, 'isActive' => (bool) $c->is_active];
    }
}
