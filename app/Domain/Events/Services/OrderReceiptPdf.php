<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Payment;
use App\Domain\Events\Models\PaymentStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprovante de compra do pedido em PDF (logo do header + dados do pagador,
 * forma de pagamento, identificador, data/hora e itens). Espelha
 * TicketReceiptPdf. `bytes()` alimenta o anexo do e-mail; `download()` a rota.
 */
class OrderReceiptPdf
{
    public function download(Order $order): Response
    {
        return $this->render($order)->download("comprovante-{$order->code}.pdf");
    }

    public function bytes(Order $order): string
    {
        return $this->render($order)->output();
    }

    private function render(Order $order): \Barryvdh\DomPDF\PDF
    {
        $order->loadMissing(['event', 'tickets.ticketType', 'payments.status']);

        $payment = $order->payments()
            ->whereIn('status_id', PaymentStatus::idsFor([PaymentStatus::PAID]))
            ->latest('paid_at')
            ->first();

        $logoPath = public_path('logo.png');
        $logoData = is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        return Pdf::loadView('pdf.order-receipt', [
            'order' => $order,
            'logoData' => $logoData,
            'paymentLabel' => $this->paymentLabel($payment),
            'payerDocument' => $this->formatDocument($order->buyer_document),
            'paidAt' => $payment?->paid_at ?? $order->created_at,
        ])->setPaper('a4', 'portrait');
    }

    private function paymentLabel(?Payment $payment): string
    {
        if ($payment === null) {
            return 'Cortesia / Gratuito';
        }

        $labels = ['pix' => 'Pix', 'boleto' => 'Boleto', 'card' => 'Cartão de crédito/débito', 'manual' => 'Manual'];
        $label = $labels[$payment->method] ?? $payment->method;

        if ($payment->method === 'card') {
            $label .= $payment->card_brand ? ' — '.ucfirst($payment->card_brand) : '';
            $label .= $payment->card_last4 ? ' final '.$payment->card_last4 : '';
            $label .= ($payment->installments && $payment->installments > 1) ? ' ('.$payment->installments.'×)' : '';
        }

        return $label;
    }

    private function formatDocument(?string $document): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $document);

        if (strlen($digits) === 11) {
            return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9);
        }
        if (strlen($digits) === 14) {
            return substr($digits, 0, 2).'.'.substr($digits, 2, 3).'.'.substr($digits, 5, 3).'/'.substr($digits, 8, 4).'-'.substr($digits, 12);
        }

        return $document ?: null;
    }
}
