<?php

namespace App\Providers;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Ticket;
use App\Policies\EventPolicy;
use App\Policies\OrderPolicy;
use App\Policies\TicketPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fakes como singletons (banco simulado consistente); resolver por config.
        $this->app->singleton(\App\Domain\Events\Payments\FakePixGateway::class);
        $this->app->singleton(\App\Domain\Events\Payments\FakeCardGateway::class);
        $this->app->singleton(\App\Domain\Events\Payments\PaymentGateways::class);
    }

    public function boot(): void
    {
        // Models do domínio ficam fora de App\Models — registro explícito.
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);

        $this->configureRateLimiters();
    }

    /**
     * Limitadores nomeados da autenticação
     * (specs/002-auth-inscrito/research.md, Decisão 7).
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('auth-email', function (Request $request) {
            return Limit::perMinute(1)->by('resend:'.($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('auth-forgot', function (Request $request) {
            return Limit::perMinute(3)->by('forgot:'.$request->ip());
        });
    }
}
