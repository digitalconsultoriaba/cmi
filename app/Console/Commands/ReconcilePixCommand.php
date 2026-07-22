<?php

namespace App\Console\Commands;

use App\Domain\Events\Services\ReconcilePayments;
use Illuminate\Console\Command;

/**
 * Reconciliação de PIX (spec 015): verifica no microsserviço/SICOOB se as
 * cobranças PIX pendentes foram creditadas — cobre o caso da verificação em tela
 * não ter ocorrido (aba fechada). Baixa as creditadas e marca "desistência" as
 * que passaram de 1h sem pagamento. Agendado a cada 10 min.
 */
class ReconcilePixCommand extends Command
{
    protected $signature = 'payments:reconcile-pix {--give-up=60 : Minutos até marcar desistência}';

    protected $description = 'Reconcilia cobranças PIX pendentes com o SICOOB (baixa creditadas; desiste após 1h)';

    public function handle(ReconcilePayments $reconcile): int
    {
        $summary = $reconcile->reconcilePendingPix((int) $this->option('give-up'));

        $this->info(sprintf(
            'PIX — Verificados: %d | Baixados: %d | Expirados: %d | Desistências: %d | Erros: %d',
            $summary['checked'], $summary['settled'], $summary['expired'],
            $summary['abandoned'], $summary['errors'],
        ));

        return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
