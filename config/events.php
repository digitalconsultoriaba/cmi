<?php

return [
    // Limite anti-abuso de ingressos por pedido (spec 004, FR-017)
    'max_tickets_per_order' => (int) env('EVENTS_MAX_TICKETS_PER_ORDER', 20),

    // Política de reembolso (spec 006 — decisão do organizador em 2026-07-03):
    // 100% até N dias antes do evento; piso legal de N dias após a compra (CDC).
    'refund_full_until_days' => (int) env('EVENTS_REFUND_FULL_UNTIL_DAYS', 7),
    'refund_purchase_grace_days' => (int) env('EVENTS_REFUND_PURCHASE_GRACE_DAYS', 7),

    // Fuso oficial do evento (spec 008): filtros de período e datas em
    // relatórios são interpretados neste fuso (armazenamento segue em UTC).
    'timezone' => env('EVENTS_TIMEZONE', 'America/Sao_Paulo'),
];
