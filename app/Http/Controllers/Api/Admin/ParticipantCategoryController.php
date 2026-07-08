<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\ParticipantCategory;
use App\Domain\Events\Models\ParticipantField;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ParticipantCategoryRequest;
use App\Http\Requests\Admin\ParticipantFieldRequest;
use App\Http\Resources\Admin\ParticipantCategoryResource;
use App\Http\Resources\Admin\ParticipantFieldResource;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParticipantCategoryController extends Controller
{
    public function index(Event $event)
    {
        return ParticipantCategoryResource::collection(
            $event->participantCategories()->with('fields')->get()
        );
    }

    public function store(ParticipantCategoryRequest $request, Event $event)
    {
        $category = $event->participantCategories()->create($request->validated());

        return ParticipantCategoryResource::make($category->fresh('fields'))->response()->setStatusCode(201);
    }

    public function update(ParticipantCategoryRequest $request, Event $event, ParticipantCategory $category)
    {
        $this->assertOwnership($event, $category);
        $category->update($request->validated());

        return ParticipantCategoryResource::make($category->fresh('fields'));
    }

    public function destroy(Event $event, ParticipantCategory $category)
    {
        $this->assertOwnership($event, $category);
        $category->delete();

        return ApiResponse::data(['deleted' => true]);
    }

    // ── Campos da categoria ──────────────────────────────────────────

    public function storeField(ParticipantFieldRequest $request, Event $event, ParticipantCategory $category)
    {
        $this->assertOwnership($event, $category);
        $field = $category->fields()->create($request->validated());

        return ParticipantFieldResource::make($field)->response()->setStatusCode(201);
    }

    public function updateField(ParticipantFieldRequest $request, Event $event, ParticipantCategory $category, ParticipantField $field)
    {
        $this->assertField($event, $category, $field);
        $field->update($request->validated());

        return ParticipantFieldResource::make($field->fresh());
    }

    public function destroyField(Event $event, ParticipantCategory $category, ParticipantField $field)
    {
        $this->assertField($event, $category, $field);
        $field->delete();

        return ApiResponse::data(['deleted' => true]);
    }

    public function reorderFields(Request $request, Event $event, ParticipantCategory $category)
    {
        $this->assertOwnership($event, $category);
        $order = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ])['order'];

        DB::transaction(function () use ($category, $order) {
            foreach ($order as $i => $id) {
                $category->fields()->where('id', $id)->update(['sort' => $i]);
            }
        });

        return ParticipantFieldResource::collection($category->fresh('fields')->fields);
    }

    private function assertOwnership(Event $event, ParticipantCategory $category): void
    {
        abort_unless($category->event_id === $event->id, 404);
    }

    private function assertField(Event $event, ParticipantCategory $category, ParticipantField $field): void
    {
        $this->assertOwnership($event, $category);
        abort_unless($field->participant_category_id === $category->id, 404);
    }
}
