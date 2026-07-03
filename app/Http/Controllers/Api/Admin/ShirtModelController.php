<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventShirtModel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShirtModelRequest;
use App\Http\Resources\Admin\ShirtModelResource;
use App\Support\ApiResponse;

class ShirtModelController extends Controller
{
    public function index(Event $event)
    {
        return ShirtModelResource::collection(
            $event->shirtModels()->with('sizes')->orderBy('sort')->orderBy('id')->get()
        );
    }

    public function store(ShirtModelRequest $request, Event $event)
    {
        $model = $event->shirtModels()->create($request->validated());

        return ShirtModelResource::make($model->load('sizes'))->response()->setStatusCode(201);
    }

    public function update(ShirtModelRequest $request, Event $event, EventShirtModel $shirtModel)
    {
        $shirtModel->update($request->validated());

        return ShirtModelResource::make($shirtModel->fresh()->load('sizes'));
    }

    public function destroy(Event $event, EventShirtModel $shirtModel)
    {
        $hasSales = $shirtModel->sizes->contains(fn ($size) => $size->hasSales());

        if ($hasSales) {
            throw new DomainRuleViolation(
                'Modelo com camisas vendidas não pode ser excluído — desative-o.',
                'has_sales'
            );
        }

        $shirtModel->delete();

        return ApiResponse::data(null);
    }
}
