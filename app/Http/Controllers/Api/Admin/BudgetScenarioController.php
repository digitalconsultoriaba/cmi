<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\BudgetPlan;
use App\Domain\Events\Models\BudgetScenario;
use App\Domain\Events\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BudgetScenarioRequest;
use App\Http\Resources\Admin\BudgetScenarioResource;

class BudgetScenarioController extends Controller
{
    public function upsert(BudgetScenarioRequest $request, Event $event, string $key)
    {
        abort_unless(in_array($key, BudgetScenario::KEYS, true), 404);

        $plan = BudgetPlan::query()->firstOrCreate(['event_id' => $event->id]);
        $plan->scenarios()->updateOrCreate(
            ['key' => $key],
            $request->columns(),
        );

        // Instância "não recém-criada" → resposta 200 (não 201).
        return BudgetScenarioResource::make($plan->scenarios()->where('key', $key)->first());
    }
}
