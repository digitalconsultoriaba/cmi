<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventShirtModel;
use App\Domain\Events\Models\EventShirtSize;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShirtSizeRequest;
use App\Http\Resources\Admin\ShirtModelResource;
use App\Support\ApiResponse;

class ShirtSizeController extends Controller
{
    public function store(ShirtSizeRequest $request, Event $event, EventShirtModel $shirtModel)
    {
        $shirtModel->sizes()->create([
            ...$request->validated(),
            'event_id' => $event->id,
        ]);

        return ShirtModelResource::make($shirtModel->fresh()->load('sizes'))
            ->response()->setStatusCode(201);
    }

    public function update(ShirtSizeRequest $request, Event $event, EventShirtModel $shirtModel, EventShirtSize $size)
    {
        $data = $request->validated();

        // Estoque nunca abaixo do vendido (data-model, regra 5)
        if (array_key_exists('stock_quantity', $data) && $data['stock_quantity'] !== null
            && $data['stock_quantity'] < $size->sold_count) {
            throw new DomainRuleViolation(
                'O estoque não pode ficar abaixo do já vendido ('.$size->sold_count.').',
                'stock_below_sold'
            );
        }

        $size->update($data);

        return ShirtModelResource::make($shirtModel->fresh()->load('sizes'));
    }

    public function destroy(Event $event, EventShirtModel $shirtModel, EventShirtSize $size)
    {
        if ($size->hasSales()) {
            throw new DomainRuleViolation(
                'Tamanho com camisas vendidas não pode ser excluído — desative-o.',
                'has_sales'
            );
        }

        $size->delete();

        return ApiResponse::data(null);
    }
}
