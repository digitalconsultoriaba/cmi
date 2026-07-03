<?php

namespace App\Providers;

use App\Domain\Events\Models\Event;
use App\Policies\EventPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Models do domínio ficam fora de App\Models — registro explícito.
        Gate::policy(Event::class, EventPolicy::class);
    }
}
