<?php

namespace App\Domain\Events\Services\Translation;

/**
 * Provedor de tradução automática (spec 013). Fica atrás de contrato para
 * trocar sem reescrever (analogia ao PaymentGatewayContract). Credenciais de
 * qualquer provedor real vêm de .env — nunca do VCS (constituição IV).
 */
interface TranslationProviderContract
{
    /** Traduz um texto de um idioma para outro. Vazio = sem tradução. */
    public function translate(string $text, string $from, string $to): string;
}
