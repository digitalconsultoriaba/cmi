<?php

namespace Tests\Feature\Foundation;

use App\Domain\Events\Models\CourtesyVoucher;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventShirtModel;
use App\Domain\Events\Models\EventShirtSize;
use App\Domain\Events\Models\LandingBlock;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Payment;
use App\Domain\Events\Models\PaymentStatus;
use App\Domain\Events\Models\Sponsorship;
use App\Domain\Events\Models\SponsorshipInstallment;
use App\Domain\Events\Models\SupportCase;
use App\Domain\Events\Models\SupportCaseNote;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketLot;
use App\Domain\Events\Models\TicketType;
use App\Domain\Events\Models\WebhookEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * US2 — grafo completo do domínio (data-model.md, diagrama de relacionamentos).
 */
class DomainGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_grafo_completo_do_dominio_se_relaciona_corretamente(): void
    {
        $buyer = User::factory()->create();
        $event = Event::factory()->published()->create();

        $type = TicketType::factory()->create(['event_id' => $event->id]);
        $lot = TicketLot::factory()->create(['event_id' => $event->id, 'ticket_type_id' => $type->id]);
        $block = LandingBlock::factory()->create(['event_id' => $event->id, 'type' => 'hero']);

        $shirtModel = EventShirtModel::factory()->create(['event_id' => $event->id]);
        $shirtSize = EventShirtSize::factory()->create([
            'event_id' => $event->id,
            'shirt_model_id' => $shirtModel->id,
        ]);

        $order = Order::factory()->create(['event_id' => $event->id, 'buyer_user_id' => $buyer->id]);
        $ticket = Ticket::factory()->create([
            'order_id' => $order->id,
            'event_id' => $event->id,
            'ticket_type_id' => $type->id,
            'ticket_lot_id' => $lot->id,
            'shirt_model_id' => $shirtModel->id,
            'shirt_size_id' => $shirtSize->id,
        ]);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'amount' => '150.00',
            'method' => 'pix',
            'provider' => 'sicoob',
            'provider_charge_id' => 'txid-demo-1',
            'status_id' => PaymentStatus::idFor(PaymentStatus::PENDING),
        ]);

        $voucher = CourtesyVoucher::query()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $type->id,
        ]);

        $sponsorship = Sponsorship::query()->create([
            'event_id' => $event->id,
            'company_name' => 'Empresa Apoiadora',
            'total_amount' => '1000.00',
        ]);
        $installment = SponsorshipInstallment::query()->create([
            'sponsorship_id' => $sponsorship->id,
            'number' => 1,
            'amount' => '1000.00',
        ]);

        $case = SupportCase::query()->create([
            'event_id' => $event->id,
            'order_id' => $order->id,
            'ticket_id' => $ticket->id,
            'user_id' => $buyer->id,
            'type' => 'question',
        ]);
        $note = SupportCaseNote::query()->create([
            'support_case_id' => $case->id,
            'author_user_id' => $buyer->id,
            'body' => 'Dúvida sobre o evento.',
            'from_attendee' => true,
        ]);

        $webhook = WebhookEvent::query()->create([
            'provider' => 'sicoob',
            'external_id' => 'evt-1',
            'payload' => ['tipo' => 'pix'],
            'received_at' => now(),
        ]);

        // hasMany do evento
        $this->assertTrue($event->ticketTypes->contains($type));
        $this->assertTrue($event->ticketLots->contains($lot));
        $this->assertTrue($event->landingBlocks->contains($block));
        $this->assertTrue($event->shirtModels->contains($shirtModel));
        $this->assertTrue($event->shirtSizes->contains($shirtSize));
        $this->assertTrue($event->orders->contains($order));
        $this->assertTrue($event->tickets->contains($ticket));
        $this->assertTrue($event->courtesyVouchers->contains($voucher));
        $this->assertTrue($event->sponsorships->contains($sponsorship));
        $this->assertTrue($event->supportCases->contains($case));

        // hierarquias e belongsTo
        $this->assertTrue($shirtModel->sizes->contains($shirtSize));
        $this->assertTrue($type->lots->contains($lot));
        $this->assertTrue($order->tickets->contains($ticket));
        $this->assertTrue($order->payments->contains($payment));
        $this->assertTrue($order->buyerUser->is($buyer));
        $this->assertTrue($ticket->order->is($order));
        $this->assertTrue($ticket->ticketLot->is($lot));
        $this->assertTrue($ticket->shirtSize->is($shirtSize));
        $this->assertTrue($sponsorship->installments->contains($installment));
        $this->assertTrue($case->notes->contains($note));
        $this->assertTrue($note->author->is($buyer));
        $this->assertNotNull($webhook->id);

        // Códigos públicos não sequenciais (FR-006)
        $this->assertMatchesRegularExpression('/^ORD-[A-Z0-9]{10}$/', $order->code);
        $this->assertMatchesRegularExpression('/^TCK-[A-Z0-9]{10}$/', $ticket->code);
        $this->assertMatchesRegularExpression('/^CTY-[A-Z0-9]{10}$/', $voucher->code);
    }
}
