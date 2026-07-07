<?php

namespace Database\Seeders;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventDay;
use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\Payment;
use App\Domain\Events\Models\PaymentStatus;
use App\Domain\Events\Models\Role;
use App\Domain\Events\Models\TicketLot;
use App\Domain\Events\Models\TicketStatus;
use App\Domain\Events\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cenário de demonstração do Seminário (checkout 014 + check-in 012 + financeiro
 * 010): 30 inscrições individuais (15 GLMEES / 15 outra potência) + 5 pedidos em
 * bloco de 4 irmãos (20). Tipos só Individual e Cortesia; 3 lotes (250/300/350);
 * evento de 2 dias (hoje 14h + amanhã) para dar baixa no check-in; pedidos pagos
 * geram contas a receber (via OrderObserver). Idempotente — limpa antes.
 */
class SeminarioDemoSeeder extends Seeder
{
    private const POTENCIAS = [
        'Grande Oriente do Brasil', 'Grande Loja de São Paulo', 'Grande Oriente de Portugal',
        'Gran Logia de la Argentina', 'Grande Loja do Rio de Janeiro', 'Grande Oriente do Paraná',
        'Gran Logia del Uruguay', 'Gran Logia de Chile',
    ];

    private const PAISES = ['Brasil', 'Argentina', 'Portugal', 'Uruguai', 'Chile', 'Paraguai'];

    private const CIDADES = [
        'Vitória', 'São Paulo', 'Rio de Janeiro', 'Curitiba', 'Lisboa',
        'Buenos Aires', 'Porto', 'Montevidéu', 'Santiago', 'Assunção',
    ];

    private const CARGOS = ['Venerável Mestre', '1º Vigilante', '2º Vigilante', 'Orador', 'Secretário', 'Tesoureiro', null, null];

    /** Lotes vigentes para distribuir os ingressos (round-robin). */
    private array $lots = [];
    private int $lotIdx = 0;

    public function run(): void
    {
        $event = Event::query()->where('slug', 'seminario-internacional-2026')->first();
        if ($event === null) {
            $this->command?->warn('Evento demo não encontrado — pulei o SeminarioDemoSeeder.');

            return;
        }

        $this->clearOrders($event);
        $individual = $this->configure($event);
        $this->setupDays($event);

        $glmeesLojas = $event->affiliations()->inRandomOrder()->limit(15)->pluck('name')->all();
        if ($glmeesLojas === []) {
            $glmeesLojas = ['1 - INDEPENDÊNCIA'];
        }

        // 30 inscrições individuais (1 participante por pedido).
        foreach (range(1, 15) as $i) {
            $this->order($event, $individual, [
                $this->participant('glmees', ['loja' => $glmeesLojas[($i - 1) % count($glmeesLojas)]]),
            ]);
        }
        foreach (range(1, 15) as $i) {
            $this->order($event, $individual, [$this->participant('outra_potencia')]);
        }

        // 5 pedidos em bloco, 4 irmãos cada (categorias variadas).
        foreach (range(1, 5) as $b) {
            $group = [];
            foreach (range(1, 4) as $k) {
                $isGlmees = ($b + $k) % 2 === 0;
                $group[] = $isGlmees
                    ? $this->participant('glmees', ['loja' => $glmeesLojas[array_rand($glmeesLojas)]])
                    : $this->participant('outra_potencia');
            }
            $this->order($event, $individual, $group);
        }

        // Atualiza o cache de vendidos por lote (a aba Ingressos lê esse contador).
        foreach ($this->lots as $lot) {
            $lot->recountSold();
        }

        $this->createSponsorships($event);
        $this->createExpenses($event);

        $this->command?->info('Demo criada: 30 individuais + 5 blocos (20) = 50 inscrições pagas; contas a receber geradas.');
    }

    /** Ajusta tipos (só Individual + Cortesia) e lotes (250/300/350). Devolve o tipo Individual. */
    private function configure(Event $event): TicketType
    {
        $tz = config('events.timezone');
        $event->update([
            'starts_at' => Carbon::today($tz)->setTime(14, 0),
            'ends_at' => Carbon::tomorrow($tz)->setTime(18, 0),
            'sales_start_at' => now()->subDays(10),
            'sales_end_at' => now()->addDays(30),
            'total_capacity' => 500,
            'status_id' => EventStatus::idFor(EventStatus::PUBLISHED),
            'visible_on_site' => true,
            'support_whatsapp' => '(27) 99876-5432',
            'support_email' => 'atendimento@seminariocmi.org.br',
        ]);

        $individual = $event->ticketTypes()->where('name', 'Individual')->first()
            ?? $event->ticketTypes()->create(['name' => 'Individual', 'price' => '250.00', 'capacity' => 500, 'sort' => 0]);
        $individual->update(['price' => '250.00', 'is_active' => true]);

        $event->ticketTypes()->firstOrCreate(
            ['name' => 'Cortesia'],
            ['price' => '0.00', 'is_courtesy' => true, 'audience' => 'guest', 'sort' => 2, 'is_active' => true]
        )->update(['is_active' => true]);

        // Só Individual e Cortesia visíveis: desativa os demais (ex.: Casal).
        $event->ticketTypes()->whereNotIn('name', ['Individual', 'Cortesia'])->update(['is_active' => false]);

        // Lotes: valores 250/300/350. Todos abertos para poder distribuir os
        // ingressos vendidos entre eles (janelas amplas nesta demo).
        $event->ticketLots()->forceDelete();
        $this->lots = [
            $event->ticketLots()->create([
                'name' => '1º lote', 'price_override' => '250.00',
                'starts_at' => now()->subDays(20), 'ends_at' => now()->addDays(60), 'is_active' => true, 'sort' => 0,
            ]),
            $event->ticketLots()->create([
                'name' => '2º lote', 'price_override' => '300.00',
                'starts_at' => now()->subDays(20), 'ends_at' => now()->addDays(60), 'is_active' => true, 'sort' => 1,
            ]),
            $event->ticketLots()->create([
                'name' => '3º lote', 'price_override' => '350.00',
                'starts_at' => now()->subDays(20), 'ends_at' => now()->addDays(60), 'is_active' => true, 'sort' => 2,
            ]),
        ];
        $this->lotIdx = 0;

        return $individual;
    }

    /** Dia 1 = hoje 14h (fim 23:59 p/ dar baixa o dia todo); Dia 2 = amanhã. */
    private function setupDays(Event $event): void
    {
        $tz = config('events.timezone');
        $event->eventDays()->forceDelete();

        $event->eventDays()->create([
            'day_number' => 1, 'event_date' => Carbon::today($tz)->toDateString(),
            'starts_at' => '14:00:00', 'ends_at' => '23:59:00', 'label' => 'Abertura',
        ]);
        $event->eventDays()->create([
            'day_number' => 2, 'event_date' => Carbon::tomorrow($tz)->toDateString(),
            'starts_at' => '09:00:00', 'ends_at' => '18:00:00', 'label' => 'Encerramento',
        ]);
    }

    /** Monta os dados (snapshot) de um participante por categoria. */
    private function participant(string $category, array $extra = []): array
    {
        $name = fake()->unique()->name('male');
        $fields = $extra;
        if ($category === 'outra_potencia') {
            $fields += [
                'potencia' => self::POTENCIAS[array_rand(self::POTENCIAS)],
                'pais' => self::PAISES[array_rand(self::PAISES)],
                'cidade' => self::CIDADES[array_rand(self::CIDADES)],
            ];
        }
        $cargo = self::CARGOS[array_rand(self::CARGOS)];
        if ($cargo !== null) {
            $fields['cargo'] = $cargo;
        }
        $fields['whatsapp'] = '(27) 9'.fake()->numerify('####-####');

        return ['name' => $name, 'category' => $category, 'fields' => $fields];
    }

    /** Cria um pedido PAGO com N participantes (Individual); observer gera a conta a receber. */
    private function order(Event $event, TicketType $type, array $participants): void
    {
        DB::transaction(function () use ($event, $type, $participants) {
            $buyerData = $participants[0];
            $buyer = $this->user($buyerData['name']);

            $order = $event->orders()->create([
                'buyer_user_id' => $buyer->id,
                'buyer_name' => $buyer->name,
                'buyer_email' => $buyer->email,
                'total_amount' => '0.00',
                'status_id' => OrderStatus::idFor(OrderStatus::PAID),
            ]);

            $total = 0.0;
            foreach ($participants as $idx => $p) {
                $participantUser = $idx === 0 ? $buyer : $this->user($p['name']);
                $lot = $this->lots[$this->lotIdx++ % count($this->lots)]; // distribui entre os 3 lotes
                $price = $lot->price_override;
                $order->tickets()->create([
                    'event_id' => $event->id,
                    'ticket_type_id' => $type->id,
                    'ticket_lot_id' => $lot->id,
                    'participant_name' => $p['name'],
                    'participant_email' => $participantUser->email,
                    'participant_user_id' => $participantUser->id,
                    'participant_category_key' => $p['category'],
                    'participant_fields' => $p['fields'],
                    'unit_price' => $price,
                    'status_id' => TicketStatus::idFor(TicketStatus::CONFIRMED),
                ]);
                $total += (float) $price;
            }

            Payment::query()->create([
                'order_id' => $order->id,
                'amount' => number_format($total, 2, '.', ''),
                'method' => 'manual',
                'provider' => 'manual',
                'provider_charge_id' => 'demo-'.$order->code,
                'status_id' => PaymentStatus::idFor(PaymentStatus::PAID),
                'paid_at' => now(),
            ]);

            // Recalcula o total e salva → OrderObserver cria a conta a receber (quitada).
            $order->update(['total_amount' => number_format($total, 2, '.', '')]);
        });
    }

    /** Dois patrocínios (Sicoob/Sicredi) de R$ 50k em 5× de R$ 10k, 1ª parcela recebida. */
    private function createSponsorships(Event $event): void
    {
        $service = app(\App\Domain\Events\Services\SponsorshipService::class);
        foreach (['Sicoob', 'Sicredi'] as $name) {
            $sp = $service->createWithInstallments($event, [
                'company_name' => $name,
                'total_amount' => '50000.00',
                'installments_count' => 5,
                'payment_method' => 'transfer',
                'first_due_date' => now()->startOfMonth()->toDateString(),
            ]);
            $service->payInstallment(
                $sp->installments()->where('number', 1)->first(),
                ['paid_at' => now(), 'method' => 'transfer']
            );
        }
    }

    /** Despesas do evento como contas a pagar (fluxo de caixa distribuído). */
    private function createExpenses(Event $event): void
    {
        $fe = \App\Domain\Events\Models\FinancialEntry::class;
        $fe::where('event_id', $event->id)->where('origin', 'event_expense')->forceDelete();

        // [descrição, valor, id da categoria de despesa]
        $items = [
            ['Locação Hotel', '23000.00', 9], ['Sonorização/Infraestrutura', '26000.00', 17],
            ['Gráfica/Crachá', '1000.00', 14], ['Logística', '5000.00', 20],
            ['Mimos para palestrantes', '4000.00', 15], ['Coffe break', '3000.00', 8],
            ['Coquetel', '61800.00', 8], ['Fotógrafo/Filmagem', '3000.00', 18],
            ['Chopp', '3200.00', 8], ['Vinho', '3200.00', 8], ['Wisky', '2000.00', 8],
        ];

        foreach ($items as $i => [$desc, $amount, $cat]) {
            $fe::create([
                'direction' => 'payable',
                'description' => $desc,
                'amount' => $amount,
                'settled_amount' => '0.00',
                'category_id' => $cat,
                'event_id' => $event->id,
                'origin' => 'event_expense',
                'due_date' => now()->addDays(3 + $i * 4)->toDateString(),
            ]);
        }
    }

    private function user(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'email' => fake()->unique()->safeEmail()]);
        $user->assignRole(Role::ATTENDEE);

        return $user;
    }

    /** Limpa pedidos/financeiro/check-ins do evento (hard delete; sem migrate:fresh). */
    private function clearOrders(Event $event): void
    {
        $id = $event->id;
        $orderIds = DB::table('orders')->where('event_id', $id)->pluck('id');

        DB::table('ticket_day_checkins')->where('event_id', $id)->delete();

        $scIds = DB::table('support_cases')->where('event_id', $id)->pluck('id');
        if ($scIds->isNotEmpty()) {
            DB::table('support_case_notes')->whereIn('support_case_id', $scIds)->delete();
            DB::table('support_cases')->whereIn('id', $scIds)->delete();
        }

        if ($orderIds->isNotEmpty()) {
            $entryIds = DB::table('financial_entries')
                ->where('source_type', Order::class)->whereIn('source_id', $orderIds)->pluck('id');
            if ($entryIds->isNotEmpty()) {
                DB::table('financial_settlements')->whereIn('entry_id', $entryIds)->delete();
                DB::table('financial_entries')->whereIn('id', $entryIds)->delete();
            }
            DB::table('payments')->whereIn('order_id', $orderIds)->delete();
            DB::table('tickets')->whereIn('order_id', $orderIds)->delete();
            DB::table('orders')->whereIn('id', $orderIds)->delete();
        }

        // Patrocínios + parcelas + espelho financeiro do evento.
        $spIds = DB::table('sponsorships')->where('event_id', $id)->pluck('id');
        if ($spIds->isNotEmpty()) {
            $instIds = DB::table('sponsorship_installments')->whereIn('sponsorship_id', $spIds)->pluck('id');
            if ($instIds->isNotEmpty()) {
                $spEntryIds = DB::table('financial_entries')
                    ->where('source_type', \App\Domain\Events\Models\SponsorshipInstallment::class)
                    ->whereIn('source_id', $instIds)->pluck('id');
                if ($spEntryIds->isNotEmpty()) {
                    DB::table('financial_settlements')->whereIn('entry_id', $spEntryIds)->delete();
                    DB::table('financial_entries')->whereIn('id', $spEntryIds)->delete();
                }
                DB::table('sponsorship_installments')->whereIn('id', $instIds)->delete();
            }
            DB::table('sponsorships')->whereIn('id', $spIds)->delete();
        }
    }
}
