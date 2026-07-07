<?php

use App\Http\Controllers\Api\Admin\BudgetController;
use App\Http\Controllers\Api\Admin\BudgetCostItemController;
use App\Http\Controllers\Api\Admin\BudgetScenarioController;
use App\Http\Controllers\Api\Admin\BudgetSponsorshipController;
use App\Http\Controllers\Api\Admin\BudgetTicketLotController;
use App\Http\Controllers\Api\Admin\CourtesyVoucherController;
use App\Http\Controllers\Api\Admin\EventController as AdminEventController;
use App\Http\Controllers\Api\Admin\EventDayController;
use App\Http\Controllers\Api\Admin\EventTypeController;
use App\Http\Controllers\Api\Admin\LandingBlockController;
use App\Http\Controllers\Api\Admin\ShirtModelController;
use App\Http\Controllers\Api\Admin\ShirtSizeController;
use App\Http\Controllers\Api\Admin\SponsorshipController;
use App\Http\Controllers\Api\Admin\TicketLotController;
use App\Http\Controllers\Api\Admin\TicketTypeController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\Auth\GoogleController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\Auth\ProfileController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Admin\SupportQueueController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\Gate\GateController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\SupportCaseController;
use App\Http\Controllers\Api\TicketLifecycleController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\EventPanelController;
use App\Http\Controllers\Api\Admin\OverviewController;
use App\Http\Controllers\Api\Admin\ReportExportController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Treasury\FinanceController;
use App\Http\Controllers\Api\Treasury\RefundController;
use App\Http\Controllers\Api\Treasury\TreasuryController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\PublicEventController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

// ── Catálogo público (spec 004 — sem auth, binding por slug) ────────
Route::get('/public/events/{event:slug}', [PublicEventController::class, 'show']);

// ── Landing pública do Site (spec 013 — sem auth, por slug do site) ──
Route::get('/public/sites/{slug}', [\App\Http\Controllers\Api\Public\PublicSiteController::class, 'show']);

// ── Checkout do seminário (spec 014 — guest, sem auth) ───────────────
Route::get('/public/events/{event:slug}/checkout-config', [\App\Http\Controllers\Api\Public\GuestCheckoutController::class, 'checkoutConfig']);
Route::post('/public/vouchers/validate', [\App\Http\Controllers\Api\Public\GuestCheckoutController::class, 'validateVoucher']);
Route::post('/public/orders', [\App\Http\Controllers\Api\Public\GuestCheckoutController::class, 'store']);
Route::post('/public/orders/{order:code}/checkout/pix', [\App\Http\Controllers\Api\Public\GuestCheckoutController::class, 'pix']);
Route::post('/public/orders/{order:code}/checkout/card', [\App\Http\Controllers\Api\Public\GuestCheckoutController::class, 'card']);
Route::get('/public/orders/{order:code}/payment-status', [\App\Http\Controllers\Api\Public\GuestCheckoutController::class, 'paymentStatus']);
Route::post('/public/orders/{order:code}/resend-access', [\App\Http\Controllers\Api\Public\GuestCheckoutController::class, 'resendAccess']);

// ── Compra e área do inscrito (spec 004 — códigos públicos nas URLs) ─
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order:code}', [OrderController::class, 'show']);
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/{ticket:code}', [TicketController::class, 'show']);
    Route::get('/tickets/{ticket:code}/receipt', [TicketController::class, 'receipt']);

    // ── Checkout (spec 005) ──
    Route::post('/orders/{order:code}/checkout/pix', [CheckoutController::class, 'pix']);
    Route::post('/orders/{order:code}/checkout/boleto', [CheckoutController::class, 'boleto']);
    Route::post('/orders/{order:code}/checkout/card', [CheckoutController::class, 'card']);
    Route::get('/orders/{order:code}/payment-status', [CheckoutController::class, 'paymentStatus']);

    // ── Ciclo de vida (spec 006) ──
    Route::post('/tickets/{ticket:code}/cancel', [TicketLifecycleController::class, 'cancelTicket']);
    Route::post('/orders/{order:code}/cancel', [TicketLifecycleController::class, 'cancelOrder']);
    Route::post('/tickets/{ticket:code}/transfer', [TicketLifecycleController::class, 'transfer']);

    // ── Suporte do inscrito (spec 006) ──
    Route::get('/support-cases', [SupportCaseController::class, 'index']);
    Route::post('/support-cases', [SupportCaseController::class, 'store']);
    Route::get('/support-cases/{supportCase}', [SupportCaseController::class, 'show']);
    Route::post('/support-cases/{supportCase}/notes', [SupportCaseController::class, 'addNote']);
});

// ── Fila de suporte da organização (spec 006) ────────────────────────
Route::prefix('admin/support-cases')
    ->middleware(['auth:sanctum', 'require.role:admin,treasury'])
    ->group(function () {
        Route::get('/', [SupportQueueController::class, 'index']);
        Route::get('/{supportCase}', [SupportQueueController::class, 'show']);
        Route::post('/{supportCase}/notes', [SupportQueueController::class, 'addNote']);
        Route::post('/{supportCase}/finish', [SupportQueueController::class, 'finish']);
        Route::post('/{supportCase}/reopen', [SupportQueueController::class, 'reopen']);
    });

// ── Webhooks (spec 005 — sem sessão; verificação por segredo) ────────
Route::post('/webhooks/sicoob', [WebhookController::class, 'sicoob']);
Route::post('/webhooks/card', [WebhookController::class, 'card']);

// ── Portaria (spec 007) ───────────────────────────────────────────────
Route::prefix('gate')->middleware(['auth:sanctum', 'require.role:gate,admin'])->group(function () {
    Route::get('/events', [GateController::class, 'events']);
    Route::post('/checkin', [GateController::class, 'checkin']);
    Route::get('/attendance', [GateController::class, 'attendance']);
    Route::post('/days/{day}/finalize', [GateController::class, 'finalizeDay']);
});

// ── Tesouraria (spec 005) ─────────────────────────────────────────────
Route::prefix('treasury')->middleware(['auth:sanctum', 'require.role:treasury'])->group(function () {
    Route::get('/receivables', [TreasuryController::class, 'receivables']);
    Route::post('/reconcile', [TreasuryController::class, 'reconcile']);
    Route::post('/orders/{order:code}/pay-manual', [TreasuryController::class, 'payManual']);

    // ── Estornos (spec 006) ──
    Route::get('/refunds', [RefundController::class, 'index']);
    Route::post('/refunds/{supportCase}/execute', [RefundController::class, 'execute']);
});

// ── Financeiro consolidado (spec 008 — leitura: tesouraria E admin) ──
Route::prefix('treasury')->middleware(['auth:sanctum', 'require.role:treasury,admin'])->group(function () {
    Route::get('/finance', [FinanceController::class, 'show']);
    Route::get('/reports/finance.xlsx', [FinanceController::class, 'export']);
});

Route::prefix('auth')->group(function () {
    // Público
    Route::post('/register', RegisterController::class);
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])
        ->middleware('throttle:auth-forgot');
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);

    // Link assinado do e-mail (sem sessão — pode abrir em outro navegador)
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Magic link passwordless (spec 014): consumo do link é rota WEB (sessão),
    // ver routes/web.php `auth.magic`. Aqui só a solicitação por e-mail (XHR).
    Route::post('/magic/request', [\App\Http\Controllers\Api\Auth\MagicLinkController::class, 'request'])
        ->middleware('throttle:6,1');

    // Google (Socialite)
    Route::get('/google/redirect', [GoogleController::class, 'redirect']);
    Route::get('/google/callback', [GoogleController::class, 'callback']);

    // Autenticado
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', MeController::class);
        Route::post('/logout', [LoginController::class, 'logout']);
        Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:auth-email');
        // Autoatendimento da conta (spec 009)
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/password', [ProfileController::class, 'changePassword']);
        Route::post('/avatar', [ProfileController::class, 'uploadAvatar']);
    });
});

// ── Módulo Financeiro central (spec 010 — admin + financeiro) ────────
Route::prefix('finance')->middleware(['auth:sanctum', 'require.role:admin,treasury'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Api\Finance\DashboardController::class, 'show']);
    Route::get('/events/{event}/result', [\App\Http\Controllers\Api\Finance\DashboardController::class, 'eventResult']);

    Route::get('/entries', [\App\Http\Controllers\Api\Finance\EntryController::class, 'index']);
    Route::post('/entries', [\App\Http\Controllers\Api\Finance\EntryController::class, 'store']);
    Route::get('/entries/{entry}', [\App\Http\Controllers\Api\Finance\EntryController::class, 'show']);
    Route::put('/entries/{entry}', [\App\Http\Controllers\Api\Finance\EntryController::class, 'update']);
    Route::post('/entries/{entry}/settle', [\App\Http\Controllers\Api\Finance\SettlementController::class, 'settle']);
    Route::post('/entries/{entry}/reverse', [\App\Http\Controllers\Api\Finance\SettlementController::class, 'reverse']);
    Route::post('/entries/{entry}/cancel', [\App\Http\Controllers\Api\Finance\EntryController::class, 'cancel']);
    Route::post('/entries/{entry}/duplicate', [\App\Http\Controllers\Api\Finance\EntryController::class, 'duplicate']);

    Route::post('/entries/{entry}/attachments', [\App\Http\Controllers\Api\Finance\AttachmentController::class, 'store']);
    Route::get('/entries/{entry}/attachments/{attachment}', [\App\Http\Controllers\Api\Finance\AttachmentController::class, 'download']);
    Route::delete('/entries/{entry}/attachments/{attachment}', [\App\Http\Controllers\Api\Finance\AttachmentController::class, 'destroy']);

    Route::get('/categories', [\App\Http\Controllers\Api\Finance\CategoryController::class, 'index']);
    Route::post('/categories', [\App\Http\Controllers\Api\Finance\CategoryController::class, 'store']);
    Route::put('/categories/{category}', [\App\Http\Controllers\Api\Finance\CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [\App\Http\Controllers\Api\Finance\CategoryController::class, 'destroy']);

    Route::get('/people', [\App\Http\Controllers\Api\Finance\PersonController::class, 'index']);
    Route::post('/people', [\App\Http\Controllers\Api\Finance\PersonController::class, 'store']);
    Route::put('/people/{person}', [\App\Http\Controllers\Api\Finance\PersonController::class, 'update']);
    Route::delete('/people/{person}', [\App\Http\Controllers\Api\Finance\PersonController::class, 'destroy']);

    Route::get('/payment-methods', [\App\Http\Controllers\Api\Finance\PaymentMethodController::class, 'index']);
    Route::post('/payment-methods', [\App\Http\Controllers\Api\Finance\PaymentMethodController::class, 'store']);
    Route::put('/payment-methods/{paymentMethod}', [\App\Http\Controllers\Api\Finance\PaymentMethodController::class, 'update']);

    Route::get('/recurrences', [\App\Http\Controllers\Api\Finance\RecurrenceController::class, 'index']);
    Route::post('/recurrences', [\App\Http\Controllers\Api\Finance\RecurrenceController::class, 'store']);
    Route::put('/recurrences/{recurrence}', [\App\Http\Controllers\Api\Finance\RecurrenceController::class, 'update']);
    Route::delete('/recurrences/{recurrence}', [\App\Http\Controllers\Api\Finance\RecurrenceController::class, 'destroy']);

    Route::get('/reports/{type}', [\App\Http\Controllers\Api\Finance\ReportController::class, 'preview'])
        ->where('type', '[a-z-]+');
    Route::get('/reports/{type}/{format}', [\App\Http\Controllers\Api\Finance\ReportController::class, 'export'])
        ->where(['type' => '[a-z-]+', 'format' => 'xlsx|pdf|csv']);
});

// ── Painel administrativo (spec 003; 009: financeiro acessa tudo) ────
// Admin E tesouraria acessam o módulo inteiro; só a gestão de usuários da
// equipe é exclusiva do admin (fronteira: quem cria contas de equipe).
Route::prefix('admin')->middleware(['auth:sanctum', 'require.role:admin,treasury'])->scopeBindings()->group(function () {
    // ── Painel e relatórios (spec 008) ──
    Route::get('/dashboard', [DashboardController::class, 'show']);
    Route::get('/audit', [AuditLogController::class, 'index']);
    Route::get('/reports/attendees.xlsx', [ReportExportController::class, 'attendees']);
    Route::get('/reports/attendance.xlsx', [ReportExportController::class, 'attendance']);

    // ── Painel v2 escopado por evento (spec 009) ──
    Route::get('/overview', [OverviewController::class, 'show']);
    Route::prefix('events/{event}')->group(function () {
        Route::get('/dashboard', [EventPanelController::class, 'dashboard']);
        Route::get('/attendees', [EventPanelController::class, 'attendees']);
        Route::get('/attendance', [EventPanelController::class, 'attendance']);
        Route::get('/attendance-report', [EventPanelController::class, 'attendanceReport']);
        Route::get('/orders', [EventPanelController::class, 'orders']);
        Route::get('/reports/preview', [EventPanelController::class, 'reportsPreview']);
        Route::get('/reports/{type}.xlsx', [EventPanelController::class, 'reportsExport']);
    });

    // Baixa manual pelo admin (financeiro do evento); comprador nunca no
    // próprio pedido — guarda no TreasuryController (constituição, III).
    // Rota plana (binding por código, sem escopo aninhado).
    Route::post('/orders/{order:code}/pay-manual', [TreasuryController::class, 'payManual']);

    // Comprovante (PDF+QR) de qualquer ingresso — acesso do admin (spec 009)
    Route::get('/tickets/{ticket:code}/receipt', [EventPanelController::class, 'receipt']);

    // ── Ficha do cliente (spec 009): dados, compras, ingressos, mensagens ──
    // withoutScopedBindings: o usuário não é filho do evento (não há relação)
    Route::get('/events/{event}/customers/{user}', [CustomerController::class, 'show'])->withoutScopedBindings();
    Route::get('/events/{event}/customers/{user}/messages', [CustomerController::class, 'messages'])->withoutScopedBindings();
    Route::post('/events/{event}/customers/{user}/messages', [CustomerController::class, 'sendMessage'])->withoutScopedBindings();
    Route::post('/events/{event}/customers/{user}/history', [CustomerController::class, 'addHistory'])->withoutScopedBindings();
    // Cancelamento pelo staff (política de reembolso 006)
    Route::post('/tickets/{ticket:code}/cancel', [CustomerController::class, 'cancelTicket']);
    Route::post('/orders/{order:code}/cancel', [CustomerController::class, 'cancelOrder']);

    // ── Gestão de usuários da equipe (spec 009) — SÓ admin ──
    Route::middleware('require.role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });

    Route::get('/events', [AdminEventController::class, 'index']);
    Route::post('/events', [AdminEventController::class, 'store']);
    Route::get('/events/{event}', [AdminEventController::class, 'show']);
    Route::put('/events/{event}', [AdminEventController::class, 'update']);
    Route::post('/events/{event}/publish', [AdminEventController::class, 'publish']);
    Route::post('/events/{event}/visibility', [AdminEventController::class, 'visibility']);
    Route::post('/events/{event}/cancel', [AdminEventController::class, 'cancel']);
    Route::post('/events/{event}/banner', [AdminEventController::class, 'banner']);

    Route::apiResource('event-types', EventTypeController::class)->except(['show']);

    Route::prefix('events/{event}')->group(function () {
        Route::get('/ticket-types', [TicketTypeController::class, 'index']);
        Route::post('/ticket-types', [TicketTypeController::class, 'store']);
        Route::patch('/ticket-types/reorder', [TicketTypeController::class, 'reorder']);
        Route::put('/ticket-types/{ticketType}', [TicketTypeController::class, 'update']);
        Route::delete('/ticket-types/{ticketType}', [TicketTypeController::class, 'destroy']);

        Route::get('/lots', [TicketLotController::class, 'index']);
        Route::post('/lots', [TicketLotController::class, 'store']);
        Route::patch('/lots/reorder', [TicketLotController::class, 'reorder']);
        Route::put('/lots/{ticketLot}', [TicketLotController::class, 'update']);
        Route::delete('/lots/{ticketLot}', [TicketLotController::class, 'destroy']);

        Route::get('/shirt-models', [ShirtModelController::class, 'index']);
        Route::post('/shirt-models', [ShirtModelController::class, 'store']);
        Route::put('/shirt-models/{shirtModel}', [ShirtModelController::class, 'update']);
        Route::delete('/shirt-models/{shirtModel}', [ShirtModelController::class, 'destroy']);
        Route::post('/shirt-models/{shirtModel}/sizes', [ShirtSizeController::class, 'store']);
        Route::put('/shirt-models/{shirtModel}/sizes/{size}', [ShirtSizeController::class, 'update']);
        Route::delete('/shirt-models/{shirtModel}/sizes/{size}', [ShirtSizeController::class, 'destroy']);

        Route::get('/landing-blocks', [LandingBlockController::class, 'index']);
        Route::post('/landing-blocks', [LandingBlockController::class, 'store']);
        Route::patch('/landing-blocks/reorder', [LandingBlockController::class, 'reorder']);
        Route::put('/landing-blocks/{landingBlock}', [LandingBlockController::class, 'update']);
        Route::delete('/landing-blocks/{landingBlock}', [LandingBlockController::class, 'destroy']);

        Route::get('/courtesy-vouchers/stats', [CourtesyVoucherController::class, 'stats']);
        Route::get('/courtesy-vouchers', [CourtesyVoucherController::class, 'index']);
        Route::post('/courtesy-vouchers', [CourtesyVoucherController::class, 'generate']);
        Route::patch('/courtesy-vouchers/{courtesyVoucher}/distribute', [CourtesyVoucherController::class, 'distribute']);

        Route::get('/sponsorships', [SponsorshipController::class, 'index']);
        Route::post('/sponsorships', [SponsorshipController::class, 'store']);
        Route::put('/sponsorships/{sponsorship}', [SponsorshipController::class, 'update']);
        Route::post('/sponsorships/{sponsorship}/cancel', [SponsorshipController::class, 'cancel']);
        Route::post('/sponsorships/{sponsorship}/installments/{number}/pay', [SponsorshipController::class, 'payInstallment']);

        // ── Aba Orçamento (spec 011) — planejamento por evento ──
        // Filhos ligados ao plano (não ao evento) → sem scoped bindings.
        Route::get('/budget', [BudgetController::class, 'show']);
        Route::put('/budget', [BudgetController::class, 'update']);
        Route::get('/budget/comparison', [BudgetController::class, 'comparison']);
        Route::get('/budget/export.xlsx', [BudgetController::class, 'exportXlsx']);
        Route::get('/budget/export.pdf', [BudgetController::class, 'exportPdf']);

        Route::post('/budget/cost-items', [BudgetCostItemController::class, 'store']);
        Route::put('/budget/cost-items/{item}', [BudgetCostItemController::class, 'update'])->withoutScopedBindings();
        Route::delete('/budget/cost-items/{item}', [BudgetCostItemController::class, 'destroy'])->withoutScopedBindings();
        Route::post('/budget/cost-items/{item}/duplicate', [BudgetCostItemController::class, 'duplicate'])->withoutScopedBindings();
        Route::post('/budget/cost-items/{item}/generate-payable', [BudgetCostItemController::class, 'generatePayable'])->withoutScopedBindings();

        Route::post('/budget/ticket-lots', [BudgetTicketLotController::class, 'store']);
        Route::put('/budget/ticket-lots/{lot}', [BudgetTicketLotController::class, 'update'])->withoutScopedBindings();
        Route::delete('/budget/ticket-lots/{lot}', [BudgetTicketLotController::class, 'destroy'])->withoutScopedBindings();

        Route::post('/budget/sponsorships', [BudgetSponsorshipController::class, 'store']);
        Route::put('/budget/sponsorships/{sponsorship}', [BudgetSponsorshipController::class, 'update'])->withoutScopedBindings();
        Route::delete('/budget/sponsorships/{sponsorship}', [BudgetSponsorshipController::class, 'destroy'])->withoutScopedBindings();
        Route::post('/budget/sponsorships/{sponsorship}/generate-receivable', [BudgetSponsorshipController::class, 'generateReceivable'])->withoutScopedBindings();

        Route::put('/budget/scenarios/{key}', [BudgetScenarioController::class, 'upsert']);

        // ── Categorias/campos/afiliações do checkout (spec 014) ──
        Route::get('/participant-categories', [\App\Http\Controllers\Api\Admin\ParticipantCategoryController::class, 'index']);
        Route::post('/participant-categories', [\App\Http\Controllers\Api\Admin\ParticipantCategoryController::class, 'store']);
        Route::put('/participant-categories/{category}', [\App\Http\Controllers\Api\Admin\ParticipantCategoryController::class, 'update'])->withoutScopedBindings();
        Route::delete('/participant-categories/{category}', [\App\Http\Controllers\Api\Admin\ParticipantCategoryController::class, 'destroy'])->withoutScopedBindings();
        Route::post('/participant-categories/{category}/fields', [\App\Http\Controllers\Api\Admin\ParticipantCategoryController::class, 'storeField'])->withoutScopedBindings();
        Route::patch('/participant-categories/{category}/fields/reorder', [\App\Http\Controllers\Api\Admin\ParticipantCategoryController::class, 'reorderFields'])->withoutScopedBindings();
        Route::put('/participant-categories/{category}/fields/{field}', [\App\Http\Controllers\Api\Admin\ParticipantCategoryController::class, 'updateField'])->withoutScopedBindings();
        Route::delete('/participant-categories/{category}/fields/{field}', [\App\Http\Controllers\Api\Admin\ParticipantCategoryController::class, 'destroyField'])->withoutScopedBindings();

        Route::get('/affiliations', [\App\Http\Controllers\Api\Admin\AffiliationController::class, 'index']);
        Route::post('/affiliations', [\App\Http\Controllers\Api\Admin\AffiliationController::class, 'store']);
        Route::post('/affiliations/import', [\App\Http\Controllers\Api\Admin\AffiliationController::class, 'import']);
        Route::put('/affiliations/{affiliation}', [\App\Http\Controllers\Api\Admin\AffiliationController::class, 'update'])->withoutScopedBindings();
        Route::delete('/affiliations/{affiliation}', [\App\Http\Controllers\Api\Admin\AffiliationController::class, 'destroy'])->withoutScopedBindings();

        // ── Site do evento / CMS (spec 013) ──
        // Seções/itens não são filhos diretos do evento → withoutScopedBindings.
        Route::get('/site', [\App\Http\Controllers\Api\Admin\EventSiteController::class, 'show']);
        Route::put('/site', [\App\Http\Controllers\Api\Admin\EventSiteController::class, 'update']);
        Route::post('/site/publish', [\App\Http\Controllers\Api\Admin\EventSiteController::class, 'publish']);
        Route::post('/site/unpublish', [\App\Http\Controllers\Api\Admin\EventSiteController::class, 'unpublish']);
        Route::post('/site/media', [\App\Http\Controllers\Api\Admin\SiteMediaController::class, 'store']);
        Route::patch('/site/sections/reorder', [\App\Http\Controllers\Api\Admin\SiteSectionController::class, 'reorder']);
        Route::put('/site/sections/{section}', [\App\Http\Controllers\Api\Admin\SiteSectionController::class, 'update'])->withoutScopedBindings();
        Route::get('/site/sections/{section}/items', [\App\Http\Controllers\Api\Admin\SiteItemController::class, 'index'])->withoutScopedBindings();
        Route::post('/site/sections/{section}/items', [\App\Http\Controllers\Api\Admin\SiteItemController::class, 'store'])->withoutScopedBindings();
        Route::patch('/site/sections/{section}/items/reorder', [\App\Http\Controllers\Api\Admin\SiteItemController::class, 'reorder'])->withoutScopedBindings();
        Route::put('/site/sections/{section}/items/{item}', [\App\Http\Controllers\Api\Admin\SiteItemController::class, 'update'])->withoutScopedBindings();
        Route::delete('/site/sections/{section}/items/{item}', [\App\Http\Controllers\Api\Admin\SiteItemController::class, 'destroy'])->withoutScopedBindings();

        // ── Dias do evento (spec 012) ──
        Route::get('/days', [EventDayController::class, 'index']);
        Route::put('/days', [EventDayController::class, 'upsert']);
        Route::post('/days/{day}/finalize', [EventDayController::class, 'finalize'])->withoutScopedBindings();
        // Reabrir/bloquear: só admin.
        Route::middleware('require.role:admin')->group(function () {
            Route::post('/days/{day}/reopen', [EventDayController::class, 'reopen'])->withoutScopedBindings();
            Route::post('/days/{day}/block', [EventDayController::class, 'block'])->withoutScopedBindings();
            Route::post('/days/{day}/unblock', [EventDayController::class, 'unblock'])->withoutScopedBindings();
        });
    });
});
