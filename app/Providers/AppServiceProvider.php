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

        // Provedor de tradução do Site (spec 013) atrás de contrato. Null por
        // padrão (preenchimento manual); trocável por config sem reescrever.
        $this->app->bind(
            \App\Domain\Events\Services\Translation\TranslationProviderContract::class,
            fn () => app(config('site.translation.provider')
                ?: \App\Domain\Events\Services\Translation\NullTranslationProvider::class),
        );
    }

    public function boot(): void
    {
        // Models do domínio ficam fora de App\Models — registro explícito.
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(\App\Domain\Events\Models\SupportCase::class, \App\Policies\SupportCasePolicy::class);

        // Espelho financeiro (spec 010, FR-020): pedidos e parcelas de
        // patrocínio refletem contas a receber sincronizadas.
        Order::observe(\App\Domain\Events\Observers\OrderObserver::class);
        \App\Domain\Events\Models\SponsorshipInstallment::observe(
            \App\Domain\Events\Observers\SponsorshipInstallmentObserver::class
        );

        // Todo evento nasce com o Dia 1 (spec 012).
        Event::observe(\App\Domain\Events\Observers\EventObserver::class);

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

        // Checkout público (guest, sem auth): limita abuso do gateway/criação de
        // pedidos por IP.
        RateLimiter::for('public-checkout', function (Request $request) {
            return Limit::perMinute(30)->by('pub:'.$request->ip());
        });

        // Validação de voucher: barra força-bruta de códigos de gratuidade.
        RateLimiter::for('public-voucher', function (Request $request) {
            return Limit::perMinute(10)->by('vch:'.$request->ip());
        });

        // Reenvio de acesso: dispara e-mails — limite estrito por pedido/IP.
        RateLimiter::for('public-resend', function (Request $request) {
            $order = $request->route('order');
            $code = is_object($order) ? $order->code : $order;

            return Limit::perMinute(3)->by('rsnd:'.$code.'|'.$request->ip());
        });
    }
}
