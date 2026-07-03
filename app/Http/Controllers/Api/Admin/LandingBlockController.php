<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\LandingBlock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LandingBlockRequest;
use App\Http\Resources\Admin\LandingBlockResource;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LandingBlockController extends Controller
{
    public function index(Event $event)
    {
        return LandingBlockResource::collection(
            $event->landingBlocks()->orderBy('sort')->orderBy('id')->get()
        );
    }

    public function store(LandingBlockRequest $request, Event $event)
    {
        $block = $event->landingBlocks()->create($request->validated());

        return LandingBlockResource::make($block)->response()->setStatusCode(201);
    }

    public function update(LandingBlockRequest $request, Event $event, LandingBlock $landingBlock)
    {
        $landingBlock->update($request->validated());

        return LandingBlockResource::make($landingBlock->fresh());
    }

    public function destroy(Event $event, LandingBlock $landingBlock)
    {
        $landingBlock->delete();

        return ApiResponse::data(null);
    }

    public function reorder(Request $request, Event $event)
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $valid = $event->landingBlocks()->whereIn('id', $ids)->pluck('id')->all();

        if (count($valid) !== count($ids)) {
            throw new DomainRuleViolation('Lista de ordenação contém itens de outro evento.', 'invalid_reorder');
        }

        DB::transaction(function () use ($ids, $event) {
            foreach ($ids as $index => $id) {
                $event->landingBlocks()->where('id', $id)->update(['sort' => $index]);
            }
        });

        return $this->index($event);
    }
}
