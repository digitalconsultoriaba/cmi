<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ingresso do participante em PDF (identidade GLMEES: cabeçalho navy + logo,
 * QR de validação, dados do participante/evento/lote e código público — nunca
 * o id). `download()` alimenta a rota /tickets/{code}/receipt; `bytes()` o
 * anexo do e-mail de ingresso (TicketIssuedPtBr).
 */
class TicketReceiptPdf
{
    public function download(Ticket $ticket): Response
    {
        return $this->render($ticket)->download("ingresso-{$ticket->code}.pdf");
    }

    public function bytes(Ticket $ticket): string
    {
        return $this->render($ticket)->output();
    }

    private function render(Ticket $ticket): \Barryvdh\DomPDF\PDF
    {
        $ticket->loadMissing(['event', 'ticketType', 'ticketLot', 'order']);

        // Mesmo destino do QR do e-mail (URL de validação; a portaria confere no
        // check-in). PNG via GD — o DomPDF não renderiza SVG de forma confiável.
        $base = rtrim((string) config('app.frontend_url'), '/');
        $qrDataUri = (new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale' => 6,
            'quietzoneSize' => 1,
            'outputBase64' => true,
        ])))->render($base.'/validar/'.$ticket->code);

        // Triquetra (mesma do e-mail/favicon) — cabeçalho navy do ingresso.
        $logoPath = public_path('favicon-192x192.png');
        $logoData = is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        // Tamanho sob medida (pt) para o cartão caber inteiro em 1 página.
        return Pdf::loadView('pdf.ticket', [
            'ticket' => $ticket,
            'qrDataUri' => $qrDataUri,
            'logoData' => $logoData,
        ])->setPaper([0, 0, 430, 770]);
    }
}
