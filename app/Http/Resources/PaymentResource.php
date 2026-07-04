<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'method' => $this->method,
            'status' => $this->status?->slug,
            'amount' => $this->amount,
            'pixQrCode' => $this->pix_qrcode,
            'pixQrCodeSvg' => $this->pix_qrcode_image,
            'boletoLine' => $this->boleto_line,
            'boletoBarcode' => $this->boleto_barcode,
            'boletoPdfUrl' => $this->boleto_pdf_url,
            'cardBrand' => $this->card_brand,
            'cardLast4' => $this->card_last4,
            'installments' => $this->installments,
            'dueDate' => $this->due_date?->toISOString(),
            'paidAt' => $this->paid_at?->toISOString(),
        ];
    }
}
