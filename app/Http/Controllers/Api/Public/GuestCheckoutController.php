<?php

namespace App\Http\Controllers\Api\Public;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Services\CourtesyResolver;
use App\Domain\Events\Services\CreateCharge;
use App\Domain\Events\Services\GuestBuyerService;
use App\Domain\Events\Services\OrderConfirmedNotifier;
use App\Domain\Events\Services\TicketPurchaseService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CardCheckoutRequest;
use App\Http\Requests\Public\GuestOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Checkout público (guest) do seminário (spec 014). Sem auth: identidade do
 * pedido pelo `code` não sequencial; back-office completo por magic link.
 */
class GuestCheckoutController extends Controller
{
    public function __construct(
        private readonly TicketPurchaseService $purchase,
        private readonly GuestBuyerService $guestBuyer,
        private readonly CourtesyResolver $courtesy,
        private readonly CreateCharge $createCharge,
        private readonly OrderConfirmedNotifier $notifier,
    ) {
    }

    /** Dados para montar o checkout: tipos + categorias/campos + afiliações. */
    public function checkoutConfig(Event $event)
    {
        $types = $event->ticketTypes()->where('is_active', true)->orderBy('sort')->get()
            ->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'isCouple' => (bool) $type->is_couple,
                'isCourtesy' => (bool) $type->is_courtesy,
                'effectivePrice' => $event->currentLot($type)?->effectivePrice($type) ?? $type->price,
                'purchasable' => $event->currentLot($type) !== null && ! $type->soldOut() && ! $type->is_courtesy,
                'soldOut' => $type->soldOut(),
            ])->values();

        $categories = $event->participantCategories()->where('is_active', true)->with('fields')->get()
            ->map(fn ($cat) => [
                'key' => $cat->key,
                'label' => $cat->label,
                'fields' => $cat->fields->map(fn ($f) => [
                    'key' => $f->key, 'label' => $f->label, 'type' => $f->type,
                    'required' => (bool) $f->required, 'config' => $f->config,
                ])->values(),
            ])->values();

        $affiliations = $event->affiliations()->where('is_active', true)->get()
            ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values();

        $logoPath = $event->eventSite?->identity['logoPath'] ?? $event->banner_path;

        return ApiResponse::data([
            'event' => ['slug' => $event->slug, 'name' => $event->name, 'salesState' => $event->salesOpen() ? 'open' : 'closed'],
            'identityLogo' => $logoPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath) : null,
            'ticketTypes' => $types,
            'categories' => $categories,
            'affiliations' => $affiliations,
            'supportText' => 'Adicione um ou mais participantes ao carrinho, informe os dados de cada um e finalize a inscrição. Se possuir voucher de gratuidade, aplique o código diretamente no participante correspondente.',
        ]);
    }

    /** Valida um voucher (sem resgatar) para a UI aplicar por inscrição. */
    public function validateVoucher(Request $request)
    {
        $data = $request->validate([
            'event_slug' => ['required', 'string', 'exists:events,slug'],
            'code' => ['required', 'string', 'max:30'],
            'ticket_type_id' => ['nullable', 'integer'],
        ]);

        $event = Event::query()->where('slug', $data['event_slug'])->firstOrFail();
        $valid = $this->courtesy->isRedeemable($event, $data['code'], $data['ticket_type_id'] ?? null);

        return ApiResponse::data([
            'valid' => $valid,
            'message' => $valid
                ? 'Voucher aplicado com sucesso. Esta inscrição foi isenta de pagamento.'
                : 'Voucher inválido, expirado ou já utilizado. Verifique o código informado.',
        ]);
    }

    /** Finaliza: cria o pedido misto; total 0 → gratuito (sem pagamento). */
    public function store(GuestOrderRequest $request)
    {
        $event = Event::query()->where('slug', $request->validated('event_slug'))->firstOrFail();
        $buyerData = $request->validated('buyer');
        $buyer = $this->guestBuyer->resolveBuyer($buyerData['name'], $buyerData['email']);

        $order = $this->purchase->purchaseSeminar($event, $buyer, $request->validated('items'), true);

        $free = $order->status?->slug === OrderStatus::PAID;
        if ($free) {
            $this->notifier->notify($order); // gratuito: confirma e entrega já
        }

        return response()->json([
            'data' => [
                'order' => OrderResource::make($order->load(['event', 'status', 'tickets.status', 'tickets.ticketType'])),
                'payment' => ['required' => ! $free],
            ],
        ], 201);
    }

    public function pix(Request $request, Order $order)
    {
        return PaymentResource::make($this->createCharge->pix($order))->response()->setStatusCode(201);
    }

    public function card(CardCheckoutRequest $request, Order $order)
    {
        $payment = $this->createCharge->card(
            $order, $request->validated('token'), (int) $request->validated('installments'),
        );

        return PaymentResource::make($payment->fresh());
    }

    public function paymentStatus(Request $request, Order $order)
    {
        $lastPaid = $order->payments()->latest('paid_at')->whereNotNull('paid_at')->first();

        return ApiResponse::data([
            'status' => $order->status?->slug,
            'paidAt' => $lastPaid?->paid_at?->toISOString(),
        ]);
    }

    /** Reenvia o acesso do comprador + ingressos dos participantes. */
    public function resendAccess(Request $request, Order $order)
    {
        $this->notifier->notify($order);

        return ApiResponse::data(['sent' => true]);
    }
}
