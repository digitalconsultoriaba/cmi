<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\Affiliation;
use App\Domain\Events\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AffiliationRequest;
use App\Http\Resources\Admin\AffiliationResource;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AffiliationController extends Controller
{
    public function index(Event $event)
    {
        return AffiliationResource::collection($event->affiliations()->get());
    }

    public function store(AffiliationRequest $request, Event $event)
    {
        $affiliation = $event->affiliations()->create($request->validated());

        return AffiliationResource::make($affiliation)->response()->setStatusCode(201);
    }

    public function update(AffiliationRequest $request, Event $event, Affiliation $affiliation)
    {
        $this->assertOwnership($event, $affiliation);
        $affiliation->update($request->validated());

        return AffiliationResource::make($affiliation->fresh());
    }

    public function destroy(Event $event, Affiliation $affiliation)
    {
        $this->assertOwnership($event, $affiliation);
        $affiliation->delete();

        return ApiResponse::data(['deleted' => true]);
    }

    /** Importa uma lista de nomes (uma por linha) para popular a fonte. */
    public function import(Request $request, Event $event)
    {
        $names = collect(preg_split('/\r\n|\r|\n/', (string) $request->input('names', '')))
            ->map(fn ($n) => trim($n))->filter()->unique();

        $imported = 0;
        DB::transaction(function () use ($names, $event, &$imported) {
            $sort = (int) $event->affiliations()->max('sort');
            foreach ($names as $name) {
                $event->affiliations()->firstOrCreate(['name' => $name], ['sort' => ++$sort, 'is_active' => true]);
                $imported++;
            }
        });

        return ApiResponse::data(['imported' => $imported]);
    }

    private function assertOwnership(Event $event, Affiliation $affiliation): void
    {
        abort_unless($affiliation->event_id === $event->id, 404);
    }
}
