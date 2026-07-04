<?php

namespace App\Console\Commands;

use App\Domain\Events\Services\FinancialRecurrenceService;
use Illuminate\Console\Command;

class GenerateRecurrences extends Command
{
    protected $signature = 'financial:generate-recurrences';

    protected $description = 'Materializa os lançamentos financeiros recorrentes vencíveis';

    public function handle(FinancialRecurrenceService $service): int
    {
        $n = $service->generateDue();
        $this->info("Lançamentos recorrentes gerados: $n");

        return self::SUCCESS;
    }
}
