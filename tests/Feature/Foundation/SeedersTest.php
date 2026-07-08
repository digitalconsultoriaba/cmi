<?php

namespace Tests\Feature\Foundation;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\PaymentStatus;
use App\Domain\Events\Models\Role;
use App\Domain\Events\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * US4 — dados de demonstração (quickstart.md §US4).
 */
class SeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_papeis_sao_exatamente_os_quatro_com_slugs_estaveis(): void
    {
        $this->assertEqualsCanonicalizing(
            Role::ALL,
            Role::query()->pluck('slug')->all()
        );
    }

    public function test_lookups_completos_conforme_data_model(): void
    {
        $this->assertEqualsCanonicalizing(EventStatus::ALL, EventStatus::query()->pluck('slug')->all());
        $this->assertEqualsCanonicalizing(OrderStatus::ALL, OrderStatus::query()->pluck('slug')->all());
        $this->assertEqualsCanonicalizing(TicketStatus::ALL, TicketStatus::query()->pluck('slug')->all());
        $this->assertEqualsCanonicalizing(PaymentStatus::ALL, PaymentStatus::query()->pluck('slug')->all());
        $this->assertSame(6, EventType::query()->count());
    }

    public function test_usuarios_de_dev_com_papeis(): void
    {
        $admin = User::query()->where('email', 'admin@dev.local')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole(Role::ADMIN));
        $this->assertTrue(User::query()->where('email', 'tesouraria@dev.local')->first()->hasRole(Role::TREASURY));
        $this->assertTrue(User::query()->where('email', 'portaria@dev.local')->first()->hasRole(Role::GATE));
    }

    public function test_evento_de_exemplo_completo_e_publicado(): void
    {
        $event = Event::query()->where('slug', 'seminario-internacional-2026')->first();

        $this->assertNotNull($event);
        $this->assertSame(EventStatus::PUBLISHED, $event->status->slug);
        $this->assertTrue($event->salesOpen(), 'evento demo nasce vendável');

        $this->assertCount(3, $event->ticketTypes); // Individual, Cortesia (ativos) + Casal (inativo)
        $this->assertCount(3, $event->ticketLots);   // 1º/2º/3º lote (250/300/350) — SeminarioDemoSeeder
        $this->assertCount(2, $event->shirtModels);

        // Camisas com e sem estoque
        $stocks = $event->shirtSizes->pluck('stock_quantity');
        $this->assertTrue($stocks->contains(null), 'há tamanho ilimitado');
        $this->assertTrue($stocks->contains(fn ($s) => $s !== null), 'há tamanho com estoque finito');

        // Blocos de landing de todos os tipos
        $this->assertEqualsCanonicalizing(
            ['hero', 'text', 'schedule', 'speakers', 'faq', 'location', 'cta'],
            $event->landingBlocks->pluck('type')->all()
        );
    }

    public function test_seed_e_idempotente(): void
    {
        $this->seed(DatabaseSeeder::class); // segunda execução

        $this->assertSame(4, Role::query()->count());
        $this->assertSame(1, Event::query()->where('slug', 'seminario-internacional-2026')->count());
    }
}
