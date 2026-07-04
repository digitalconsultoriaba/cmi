<?php

namespace App\Http\Controllers\Api\Treasury;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\ReportExportService;
use App\Domain\Events\Services\ReportService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FinanceController extends Controller
{
    public function show(Request $request, ReportService $reports)
    {
        [$from, $to] = $this->period($request);

        return ApiResponse::data($reports->finance($this->event(), $from, $to));
    }

    /** Mesmos filtros do consolidado — a planilha reproduz a tela (SC-004). */
    public function export(Request $request, ReportExportService $exports)
    {
        [$from, $to] = $this->period($request);

        return $exports->finance($this->event(), $from, $to);
    }

    /**
     * Filtros `month`+`year` OU `from`+`to`, interpretados no fuso oficial do
     * evento e convertidos para o intervalo UTC correspondente (FR-011).
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function period(Request $request): array
    {
        $data = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12', 'required_with:year'],
            'year' => ['nullable', 'integer', 'between:2020,2100', 'required_with:month'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], [
            'to.after_or_equal' => 'O fim do período não pode ser anterior ao início.',
        ], ['month' => 'mês', 'year' => 'ano', 'from' => 'início', 'to' => 'fim']);

        $timezone = config('events.timezone');

        if (! empty($data['month']) && ! empty($data['year'])) {
            $start = Carbon::create((int) $data['year'], (int) $data['month'], 1, 0, 0, 0, $timezone);

            return [$start->copy()->utc(), $start->copy()->endOfMonth()->utc()];
        }

        return [
            ! empty($data['from'])
                ? Carbon::parse($data['from'], $timezone)->startOfDay()->utc() : null,
            ! empty($data['to'])
                ? Carbon::parse($data['to'], $timezone)->endOfDay()->utc() : null,
        ];
    }

    protected function event(): Event
    {
        $event = Event::query()->orderBy('id')->first();

        if ($event === null) {
            throw new NotFoundHttpException('Nenhum evento cadastrado.');
        }

        return $event;
    }
}
