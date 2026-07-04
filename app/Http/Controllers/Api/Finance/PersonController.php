<?php

namespace App\Http\Controllers\Api\Finance;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\FinancialPerson;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonController extends Controller
{
    public function index(Request $request)
    {
        $q = FinancialPerson::query()->orderBy('name');
        if ($k = $request->query('kind')) {
            $q->where('kind', $k);
        }

        return ApiResponse::data($q->get()->map(fn ($p) => $this->present($p))->all());
    }

    public function store(Request $request)
    {
        $p = FinancialPerson::query()->create($this->validated($request));

        return ApiResponse::data($this->present($p), 201);
    }

    public function update(Request $request, FinancialPerson $person)
    {
        $person->update($this->validated($request, true));

        return ApiResponse::data($this->present($person->fresh()));
    }

    public function destroy(FinancialPerson $person)
    {
        if ($person->entries()->exists()) {
            throw new DomainRuleViolation('Pessoa com lançamentos não pode ser excluída — inative-a.', 'has_entries');
        }
        $person->delete();

        return ApiResponse::data(null);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'kind' => [$req, Rule::in(FinancialPerson::KINDS)],
            'name' => [$req, 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function present(FinancialPerson $p): array
    {
        return [
            'id' => $p->id, 'kind' => $p->kind, 'name' => $p->name, 'document' => $p->document,
            'phone' => $p->phone, 'whatsapp' => $p->whatsapp, 'email' => $p->email,
            'notes' => $p->notes, 'isActive' => (bool) $p->is_active,
        ];
    }
}
