<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Services\ReportService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Painel do módulo (spec 009) — consolidado de todos os eventos, somente
 * leitura, derivado na consulta.
 */
class OverviewController extends Controller
{
    public function show(Request $request, ReportService $reports)
    {
        $data = $request->validate([
            'event' => ['nullable', 'integer', 'exists:events,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], [], ['from' => 'início', 'to' => 'fim']);

        $tz = config('events.timezone');
        $from = ! empty($data['from']) ? Carbon::parse($data['from'], $tz)->startOfDay()->utc() : null;
        $to = ! empty($data['to']) ? Carbon::parse($data['to'], $tz)->endOfDay()->utc() : null;

        return ApiResponse::data($reports->overview($data['event'] ?? null, $from, $to));
    }
}
