<?php

namespace App\Console\Commands;

use App\Domain\Events\Services\ReconcilePayments;
use Illuminate\Console\Command;

class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Concilia cobranças pendentes com o provedor (garantia de baixa)';

    public function handle(ReconcilePayments $reconcile): int
    {
        $summary = $reconcile->run();

        $this->info(sprintf(
            'Verificados: %d | Baixados: %d | Expirados: %d | Erros: %d',
            $summary['checked'], $summary['settled'], $summary['expired'], $summary['errors'],
        ));

        return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
