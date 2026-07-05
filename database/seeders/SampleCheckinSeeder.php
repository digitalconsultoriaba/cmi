<?php

namespace Database\Seeders;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Massa de check-in para a demo (dev): ~30 pessoas confirmadas no evento de
 * exemplo — individuais, casais e cortesias; algumas já utilizadas.
 */
class SampleCheckinSeeder extends Seeder
{
    public function run(): void
    {
        $event = Event::query()->where('slug', 'seminario-internacional-2026')->first();

        if ($event === null || $event->orders()->where('buyer_email', 'like', 'checkin-demo%')->exists()) {
            return; // sem evento demo ou já semeado (idempotente)
        }

        $individual = $event->ticketTypes()->where('name', 'Individual')->first();
        $couple = $event->ticketTypes()->where('name', 'Casal')->first();
        $courtesy = $event->ticketTypes()->where('is_courtesy', true)->first();
        $lot = $event->ticketLots()->orderBy('sort')->first();
        $gate = User::query()->where('email', 'portaria@dev.local')->first();

        $paidStatus = OrderStatus::idFor(OrderStatus::PAID);
        $confirmed = TicketStatus::idFor(TicketStatus::CONFIRMED);
        $courtesyStatus = TicketStatus::idFor(TicketStatus::COURTESY);
        $used = TicketStatus::idFor(TicketStatus::USED);

        $buyer = User::query()->firstOrCreate(
            ['email' => 'checkin-demo@dev.local'],
            ['name' => 'Comprador Demo Portaria', 'password' => 'password', 'email_verified_at' => now()]
        );
        $buyer->assignRole(\App\Domain\Events\Models\Role::ATTENDEE);

        $order = Order::query()->create([
            'event_id' => $event->id,
            'buyer_user_id' => $buyer->id,
            'buyer_name' => $buyer->name,
            'buyer_email' => 'checkin-demo@dev.local',
            'total_amount' => '0.00',
            'status_id' => $paidStatus,
        ]);

        $names = [
            'Ana Beatriz Souza', 'Bruno Carvalho', 'Carla Dias', 'Daniel Esteves',
            'Elisa Ferreira', 'Fábio Gomes', 'Gabriela Horta', 'Hugo Iglesias',
            'Íris Justino', 'João Klein', 'Karina Lopes', 'Lucas Martins',
            'Marina Nunes', 'Nelson Oliveira', 'Olívia Prado', 'Paulo Queiroz',
            'Rafaela Santos', 'Sérgio Teixeira', 'Tatiana Uchoa', 'Vera Xavier',
        ];

        $total = '0.00';

        // 20 individuais (5 já utilizados)
        foreach ($names as $index => $name) {
            $isUsed = $index < 5;
            $order->tickets()->create([
                'event_id' => $event->id,
                'ticket_type_id' => $individual->id,
                'ticket_lot_id' => $lot?->id,
                'participant_name' => $name,
                'participant_email' => 'inscrito'.($index + 1).'@demo.local',
                'unit_price' => $lot?->effectivePrice($individual) ?? $individual->price,
                'status_id' => $isUsed ? $used : $confirmed,
                'used_at' => $isUsed ? now()->subMinutes(60 - $index * 7) : null,
                'validated_by' => $isUsed ? $gate?->id : null,
            ]);
        }

        // 2 casais (4 pessoas)
        foreach ([['Roberto Alves', 'Regina Alves'], ['Marcos Lima', 'Marta Lima']] as $pair) {
            $order->tickets()->create([
                'event_id' => $event->id,
                'ticket_type_id' => $couple->id,
                'ticket_lot_id' => $lot?->id,
                'participant_name' => $pair[0],
                'companion_name' => $pair[1],
                'unit_price' => $lot?->effectivePrice($couple) ?? $couple->price,
                'status_id' => $confirmed,
            ]);
        }

        // 2 cortesias
        foreach (['Convidada Especial', 'Palestrante Convidado'] as $name) {
            $order->tickets()->create([
                'event_id' => $event->id,
                'ticket_type_id' => $courtesy->id,
                'participant_name' => $name,
                'unit_price' => '0.00',
                'is_courtesy' => true,
                'status_id' => $courtesyStatus,
            ]);
        }

        // Total coerente com os pagáveis + caches de lote
        $order->update([
            'total_amount' => number_format(
                (float) $order->tickets()->sum('unit_price'), 2, '.', ''
            ),
        ]);
        $lot?->recountSold();

        // Pagamento confirmado (demo) — sem operador → "Sistema" no financeiro.
        $order->payments()->create([
            'method' => 'manual',
            'provider' => 'manual',
            'provider_charge_id' => 'demo-'.$order->code,
            'amount' => $order->total_amount,
            'status_id' => \App\Domain\Events\Models\PaymentStatus::idFor(\App\Domain\Events\Models\PaymentStatus::PAID),
            'paid_at' => now(),
        ]);
    }
}
