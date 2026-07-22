<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reservas vencidas liberam vagas (spec 004)
Schedule::command('orders:expire')->everyFiveMinutes();

// Garantia de baixa: concilia cobranças pendentes com o provedor (spec 005)
Schedule::command('payments:reconcile')->dailyAt('04:00');

// PIX (spec 015): verifica crédito no SICOOB das cobranças pendentes (cobre aba
// fechada); baixa as creditadas e marca desistência após 1h. A cada 10 min.
Schedule::command('payments:reconcile-pix')->everyTenMinutes()->withoutOverlapping();
