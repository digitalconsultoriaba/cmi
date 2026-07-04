<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Trilha de auditoria (spec 008) — SOMENTE leitura: não existe rota de
 * escrita/edição/exclusão; o histórico é imutável pela interface (FR-009).
 */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [], ['from' => 'início', 'to' => 'fim', 'action' => 'ação']);

        $timezone = config('events.timezone');
        $query = Activity::query()->with('causer')->latest('id');

        if (! empty($data['action'])) {
            $query->where('log_name', $data['action']);
        }

        // Filtro de período no fuso oficial do evento (FR-011)
        if (! empty($data['from'])) {
            $query->where('created_at', '>=',
                Carbon::parse($data['from'], $timezone)->startOfDay()->utc());
        }
        if (! empty($data['to'])) {
            $query->where('created_at', '<=',
                Carbon::parse($data['to'], $timezone)->endOfDay()->utc());
        }

        $page = $query->paginate(25);

        return response()->json(['data' => [
            'items' => collect($page->items())->map(fn (Activity $log) => [
                'id' => $log->id,
                'action' => $log->log_name,
                'description' => $log->description,
                'subject' => [
                    'type' => $log->subject_type ? mb_strtolower(class_basename($log->subject_type)) : null,
                    'reference' => $log->properties['reference'] ?? null,
                ],
                'causer' => $log->causer ? ['name' => $log->causer->name] : null,
                'properties' => $log->properties,
                'createdAt' => $log->created_at?->toISOString(),
            ])->values(),
            'meta' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]]);
    }
}
