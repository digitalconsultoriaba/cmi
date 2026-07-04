<?php

namespace App\Domain\Events\Payments;

use Exception;

/**
 * O provedor não estorna por API (Pix/boleto no MVP) — força o fluxo
 * operacional da tesouraria (devolução por fora + registro).
 */
class RefundNotSupported extends Exception
{
}
