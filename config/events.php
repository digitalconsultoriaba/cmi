<?php

return [
    // Limite anti-abuso de ingressos por pedido (spec 004, FR-017)
    'max_tickets_per_order' => (int) env('EVENTS_MAX_TICKETS_PER_ORDER', 20),
];
