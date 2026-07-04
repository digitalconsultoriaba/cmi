<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketStatus;
use App\Domain\Events\Services\ReportExportService;
use App\Domain\Events\Services\ReportService;
use App\Domain\Events\Services\TicketReceiptPdf;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Painel v2 escopado por evento (spec 009) — dashboard, inscritos, presença,
 * prévia e export de relatório. Somente leitura; escrita reusa endpoints
 * existentes (evento, camisas, check-in via /gate).
 */
class EventPanelController extends Controller
{
    public function dashboard(Event $event, ReportService $reports)
    {
        return ApiResponse::data($reports->eventPanel($event));
    }

    public function attendees(Request $request, Event $event, ReportService $reports)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:30'],
            'type' => ['nullable', 'integer'],   // id do tipo de ingresso (contrato)
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'in:25,50,100'],
        ], [], ['from' => 'início', 'to' => 'fim']);

        return ApiResponse::data($reports->attendeesList($event, [
            'search' => $data['search'] ?? null,
            'status' => $data['status'] ?? null,
            'ticketType' => $data['type'] ?? null,
            'from' => $this->utc($data['from'] ?? null, false),
            'to' => $this->utc($data['to'] ?? null, true),
            'page' => $data['page'] ?? 1,
            'perPage' => $data['perPage'] ?? 25,
        ]));
    }

    public function attendance(Request $request, Event $event, ReportService $reports)
    {
        return ApiResponse::data(
            $reports->attendancePayload($event, $request->query('search'))
        );
    }

    /** Pedidos do evento com situação de pagamento — Financeiro (baixa). */
    public function orders(Request $request, Event $event, ReportService $reports)
    {
        return ApiResponse::data($reports->ordersList($event, $request->query('status')));
    }

    /** Comprovante PDF+QR de qualquer ingresso confirmado (acesso admin). */
    public function receipt(Ticket $ticket, TicketReceiptPdf $pdf)
    {
        $printable = in_array($ticket->status?->slug, [
            TicketStatus::PAID, TicketStatus::CONFIRMED, TicketStatus::COURTESY, TicketStatus::USED,
        ], true);

        if (! $printable) {
            throw new DomainRuleViolation(
                'O comprovante fica disponível após a confirmação do pagamento.',
                'not_confirmed'
            );
        }

        return $pdf->download($ticket);
    }

    public function reportsPreview(Request $request, Event $event, ReportService $reports)
    {
        $f = $this->reportFilters($request);

        return ApiResponse::data(
            $reports->reportPreview($event, $f['reportType'], $f['filters'])
        );
    }

    public function reportsExport(Request $request, Event $event, string $type, ReportExportService $exports)
    {
        abort_unless(in_array($type, ReportService::REPORT_TYPES, true), 404);

        $f = $this->reportFilters($request, $type);

        return $exports->eventReport($event, $type, $f['filters']);
    }

    /**
     * Normaliza filtros de relatório: separa o TIPO de relatório
     * (inscritos/financeiro/…) do filtro por tipo de ingresso, e resolve o
     * período (year/month ou from/to no fuso do evento) para UTC.
     *
     * @return array{reportType: string, filters: array}
     */
    private function reportFilters(Request $request, ?string $typeFromRoute = null): array
    {
        $data = $request->validate([
            'type' => [$typeFromRoute ? 'nullable' : 'required', 'string',
                'in:'.implode(',', ReportService::REPORT_TYPES)],
            'ticketType' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:120'],
            'year' => ['nullable', 'integer', 'between:2020,2100', 'required_with:month'],
            'month' => ['nullable', 'integer', 'between:1,12', 'required_with:year'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], ['type.in' => 'Tipo de relatório inválido.'], ['from' => 'início', 'to' => 'fim']);

        $tz = config('events.timezone');
        $from = null;
        $to = null;

        if (! empty($data['year']) && ! empty($data['month'])) {
            $start = Carbon::create((int) $data['year'], (int) $data['month'], 1, 0, 0, 0, $tz);
            $from = $start->copy()->utc();
            $to = $start->copy()->endOfMonth()->utc();
        } else {
            $from = $this->utc($data['from'] ?? null, false);
            $to = $this->utc($data['to'] ?? null, true);
        }

        return [
            'reportType' => $typeFromRoute ?? $data['type'],
            'filters' => [
                'search' => $data['search'] ?? null,
                'ticketType' => $data['ticketType'] ?? null,
                'from' => $from,
                'to' => $to,
            ],
        ];
    }

    private function utc(?string $date, bool $endOfDay): ?Carbon
    {
        if (empty($date)) {
            return null;
        }
        $tz = config('events.timezone');
        $parsed = Carbon::parse($date, $tz);

        return ($endOfDay ? $parsed->endOfDay() : $parsed->startOfDay())->utc();
    }
}
