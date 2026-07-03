<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Sponsorship;
use App\Domain\Events\Services\SponsorshipService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PayInstallmentRequest;
use App\Http\Requests\Admin\SponsorshipRequest;
use App\Http\Resources\Admin\SponsorshipResource;

class SponsorshipController extends Controller
{
    public function __construct(private readonly SponsorshipService $service)
    {
    }

    public function index(Event $event)
    {
        return SponsorshipResource::collection(
            $event->sponsorships()->with('installments')->latest('id')->get()
        );
    }

    public function store(SponsorshipRequest $request, Event $event)
    {
        $sponsorship = $this->service->createWithInstallments($event, $request->validated());

        return SponsorshipResource::make($sponsorship)->response()->setStatusCode(201);
    }

    public function update(SponsorshipRequest $request, Event $event, Sponsorship $sponsorship)
    {
        // Dados cadastrais apenas — status é recalculado das parcelas.
        $sponsorship->update($request->safe()->only([
            'company_name', 'contact', 'payment_method', 'notes',
        ]));

        return SponsorshipResource::make($sponsorship->fresh()->load('installments'));
    }

    public function cancel(Event $event, Sponsorship $sponsorship)
    {
        $sponsorship->forceFill(['status' => 'cancelled'])->save();

        return SponsorshipResource::make($sponsorship->fresh()->load('installments'));
    }

    public function payInstallment(PayInstallmentRequest $request, Event $event, Sponsorship $sponsorship, int $number)
    {
        $installment = $sponsorship->installments()->where('number', $number)->firstOrFail();

        $this->service->payInstallment($installment, $request->validated());

        return SponsorshipResource::make($sponsorship->fresh()->load('installments'));
    }
}
