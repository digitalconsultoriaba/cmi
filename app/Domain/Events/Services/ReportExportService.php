<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketStatus;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Planilhas .xlsx (spec 008) — escrita em STREAMING (openspout) direto na
 * resposta: sem materializar em memória/disco, sem limite de linhas. As
 * linhas vêm do ReportService: a planilha é a MESMA consulta da tela.
 */
class ReportExportService
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function attendees(Event $event): StreamedResponse
    {
        $tickets = $this->reports->eligibleTickets($event);

        return $this->stream('inscritos.xlsx', function (Writer $writer) use ($tickets) {
            $writer->addRow(Row::fromValues([
                'Nome', 'Vínculo', 'Contato', 'Tipo de ingresso', 'Lote',
                'Camisa', 'Ingresso', 'Situação', 'Presença',
            ]));

            foreach ($tickets as $ticket) {
                $writer->addRow(Row::fromValues([
                    $ticket->participant_name,
                    'Titular',
                    $ticket->participant_email ?? '',
                    $ticket->ticketType?->name ?? '',
                    $ticket->ticketLot?->name ?? '',
                    $this->shirt($ticket->shirtModel?->label, $ticket->shirtSize?->label),
                    $ticket->code,
                    $ticket->status?->name ?? '',
                    $this->localTime($ticket->used_at),
                ]));

                // Cada assento além do titular é uma PESSOA com linha própria
                for ($extra = 1; $extra < $this->reports->seats($ticket); $extra++) {
                    $writer->addRow(Row::fromValues([
                        $ticket->companion_name ?? 'Acompanhante de '.$ticket->participant_name,
                        'Acompanhante',
                        '',
                        $ticket->ticketType?->name ?? '',
                        $ticket->ticketLot?->name ?? '',
                        $this->shirt(
                            $ticket->companionShirtModel?->label,
                            $ticket->companionShirtSize?->label
                        ),
                        $ticket->code,
                        $ticket->status?->name ?? '',
                        $this->localTime($ticket->used_at),
                    ]));
                }
            }
        });
    }

    public function finance(Event $event, ?Carbon $from, ?Carbon $to): StreamedResponse
    {
        $payments = $this->reports->paymentsInPeriod($event, $from, $to)
            ->with(['order', 'registeredBy', 'status'])->orderBy('paid_at')->get();
        $refunds = $this->reports->refundsInPeriod($event, $from, $to)
            ->with('order')->orderBy('refunded_at')->get();

        return $this->stream('financeiro.xlsx', function (Writer $writer) use ($payments, $refunds) {
            $writer->addRow(Row::fromValues([
                'Pedido', 'Comprador', 'Forma', 'Valor (R$)', 'Baixa em',
                'Registrado por', 'Situação atual',
            ]));

            foreach ($payments as $payment) {
                $writer->addRow(Row::fromValues([
                    $payment->order?->code ?? '',
                    $payment->order?->buyer_name ?? '',
                    ReportService::METHOD_LABELS[$payment->method] ?? $payment->method,
                    $payment->amount,
                    $this->localTime($payment->paid_at),
                    $payment->registeredBy?->name ?? 'sistema',
                    $payment->status?->name ?? '',
                ]));
            }

            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['Estornos']));
            $writer->addRow(Row::fromValues([
                'Ingresso', 'Pedido', 'Valor devolvido (R$)', 'Devolvido em',
            ]));

            foreach ($refunds as $ticket) {
                $writer->addRow(Row::fromValues([
                    $ticket->code,
                    $ticket->order?->code ?? '',
                    $ticket->refund_amount,
                    $this->localTime($ticket->refunded_at),
                ]));
            }
        });
    }

    public function attendance(Event $event): StreamedResponse
    {
        $tickets = $this->reports->eligibleTickets($event);
        $validators = \App\Models\User::query()
            ->whereIn('id', $tickets->pluck('validated_by')->filter()->unique())
            ->pluck('name', 'id');

        return $this->stream('presencas.xlsx', function (Writer $writer) use ($tickets, $validators) {
            $writer->addRow(Row::fromValues([
                'Ingresso', 'Participante', 'Acompanhante', 'Pessoas',
                'Situação', 'Entrada', 'Validado por',
            ]));

            foreach ($tickets as $ticket) {
                $writer->addRow(Row::fromValues([
                    $ticket->code,
                    $ticket->participant_name,
                    $ticket->companion_name ?? '',
                    $this->reports->seats($ticket),
                    $ticket->status?->slug === TicketStatus::USED ? 'Presente' : 'Ausente',
                    $this->localTime($ticket->used_at),
                    $ticket->validated_by ? ($validators[$ticket->validated_by] ?? '') : '',
                ]));
            }
        });
    }

    private function stream(string $filename, callable $write): StreamedResponse
    {
        return response()->streamDownload(function () use ($write) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $write($writer);
            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function shirt(?string $model, ?string $size): string
    {
        if ($model === null && $size === null) {
            return 'não informado';
        }

        return trim(($model ?? '').' '.($size ?? ''));
    }

    private function localTime(?Carbon $utc): string
    {
        return $utc?->setTimezone(config('events.timezone'))->format('d/m/Y H:i') ?? '';
    }
}
