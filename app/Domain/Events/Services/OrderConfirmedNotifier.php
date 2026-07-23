<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Role;
use App\Notifications\AccessCreatedPtBr;
use App\Notifications\TicketIssuedPtBr;
use Illuminate\Support\Facades\Log;

/**
 * Entrega pós-confirmação (specs 014/015): no MESMO processo do e-mail de
 * confirmação/ingresso, garante o ACESSO de cada e-mail do pedido (comprador +
 * participantes) e entrega o ingresso individual a cada participante.
 *
 * A conta (papel `attendee` = participante) já nasce no checkout, sem senha.
 * Aqui, quem ainda não tem senha recebe uma senha temporária gerada e o e-mail
 * de acesso. Idempotente (não sobrescreve conta existente nem reenvia) e
 * defensivo — falha de e-mail nunca bloqueia o fluxo.
 */
class OrderConfirmedNotifier
{
    // Classes de caracteres (sem ambíguos O/0, I/l/1) — senha forte e legível.
    private const PW_UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const PW_LOWER = 'abcdefghijkmnpqrstuvwxyz';
    private const PW_DIGIT = '23456789';
    private const PW_SYMBOL = '!@#$%&*?-';
    private const PW_LENGTH = 12;

    public function notify(Order $order): void
    {
        $order->loadMissing('tickets.participantUser', 'tickets.ticketType', 'tickets.event', 'buyerUser', 'event');

        $this->ensureAccess($order);

        // Todo participante recebe o SEU ingresso — inclusive quando é o próprio
        // comprador (comprador que também participa recebe os 3 e-mails).
        foreach ($order->tickets as $ticket) {
            $participant = $ticket->participantUser;
            if ($participant === null) {
                continue; // e-mail do participante não informado (sem conta)
            }
            $this->safeNotify(fn () => $participant->notify(new TicketIssuedPtBr($ticket)), $ticket->code);
        }
    }

    /**
     * Garante conta de acesso (senha + papel attendee) para o comprador e cada
     * participante. Só age em contas sem senha (novas) — gera senha segura, grava
     * (cast `hashed`) e envia o e-mail de acesso. Contas já existentes (com senha)
     * são ignoradas: não sobrescreve nem reenvia.
     */
    private function ensureAccess(Order $order): void
    {
        $users = collect([$order->buyerUser])
            ->merge($order->tickets->map->participantUser)
            ->filter()
            ->unique('id');

        foreach ($users as $user) {
            if ($user->password !== null) {
                continue;
            }

            $plain = $this->generatePassword();
            // Senha temporária: cast 'hashed' grava o hash; troca obrigatória no 1º acesso.
            $user->forceFill(['password' => $plain, 'must_change_password' => true])->save();
            $user->assignRole(Role::ATTENDEE);

            $this->safeNotify(
                fn () => $user->notify(new AccessCreatedPtBr($plain, $order->event?->name)),
                $user->email,
            );
        }
    }

    /**
     * Senha temporária forte: 12 caracteres com PELO MENOS uma maiúscula, uma
     * minúscula, um dígito e um símbolo — o resto sorteado de todas as classes e
     * tudo embaralhado com random_int (CSPRNG). Sem caracteres ambíguos.
     */
    private function generatePassword(): string
    {
        $all = self::PW_UPPER.self::PW_LOWER.self::PW_DIGIT.self::PW_SYMBOL;

        $pick = fn (string $set) => $set[random_int(0, strlen($set) - 1)];

        $chars = [$pick(self::PW_UPPER), $pick(self::PW_LOWER), $pick(self::PW_DIGIT), $pick(self::PW_SYMBOL)];
        for ($i = count($chars); $i < self::PW_LENGTH; $i++) {
            $chars[] = $pick($all);
        }

        // Fisher-Yates com CSPRNG (não usar shuffle()/mt_rand).
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    private function safeNotify(callable $send, string $ref): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar e-mail de acesso/ingresso', ['ref' => $ref, 'error' => $e->getMessage()]);
        }
    }
}
