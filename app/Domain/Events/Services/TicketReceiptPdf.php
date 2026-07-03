<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprovante em PDF com QR do código público (nunca id) — T074 da base.
 */
class TicketReceiptPdf
{
    public function download(Ticket $ticket): Response
    {
        $ticket->load(['event', 'ticketType', 'shirtModel', 'shirtSize', 'order']);

        $qrSvg = QrCode::format('svg')->size(220)->margin(1)->generate($ticket->code);

        $pdf = Pdf::loadView('pdf.ticket-receipt', [
            'ticket' => $ticket,
            'qrSvg' => $qrSvg,
        ])->setPaper('a5', 'portrait');

        return $pdf->download("ingresso-{$ticket->code}.pdf");
    }
}
