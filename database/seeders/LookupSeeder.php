<?php

namespace Database\Seeders;

use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\PaymentStatus;
use App\Domain\Events\Models\TicketStatus;
use Illuminate\Database\Seeder;

/**
 * Listas de domínio (specs/001-fundacao/data-model.md, Grupo 2).
 * Idempotente: updateOrCreate por slug/nome.
 */
class LookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedStatuses(EventStatus::class, [
            'draft' => 'Rascunho',
            'published' => 'Publicado',
            'cancelled' => 'Cancelado',
            'finished' => 'Encerrado',
        ]);

        $this->seedStatuses(OrderStatus::class, [
            'pending' => 'Aguardando pagamento',
            'paid' => 'Pago',
            'partially_paid' => 'Parcialmente pago',
            'cancelled' => 'Cancelado',
            'expired' => 'Expirado',
            'refunded' => 'Estornado',
        ]);

        $this->seedStatuses(TicketStatus::class, [
            'reserved' => 'Reservado',
            'awaiting_payment' => 'Aguardando pagamento',
            'paid' => 'Pago',
            'confirmed' => 'Confirmado',
            'courtesy' => 'Cortesia',
            'cancelled' => 'Cancelado',
            'refunded' => 'Estornado',
            'transferred' => 'Transferido',
            'used' => 'Utilizado',
        ]);

        $this->seedStatuses(PaymentStatus::class, [
            'pending' => 'Pendente',
            'paid' => 'Pago',
            'failed' => 'Falhou',
            'expired' => 'Expirado',
            'refunded' => 'Estornado',
            'chargeback' => 'Chargeback',
        ]);

        foreach (['Seminário', 'Congresso', 'Palestra', 'Workshop', 'Social', 'Outro'] as $name) {
            EventType::query()->withTrashed()->updateOrCreate(['name' => $name], ['is_active' => true]);
        }
    }

    private function seedStatuses(string $model, array $items): void
    {
        $sort = 0;

        foreach ($items as $slug => $name) {
            $model::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'sort' => $sort++, 'is_active' => true]
            );
        }
    }
}
