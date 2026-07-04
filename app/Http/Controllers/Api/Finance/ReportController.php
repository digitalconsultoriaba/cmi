<?php

namespace App\Http\Controllers\Api\Finance;

use App\Domain\Events\Services\FinancialReportService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ReportController extends Controller
{
    public function __construct(private readonly FinancialReportService $reports)
    {
    }

    public function preview(Request $request, string $type)
    {
        abort_unless(in_array($type, FinancialReportService::REPORT_TYPES, true), 404);
        $filters = $this->filters($request);

        return ApiResponse::data($this->reports->reportPreview($type, $filters));
    }

    public function export(Request $request, string $type, string $format)
    {
        abort_unless(in_array($type, FinancialReportService::REPORT_TYPES, true), 404);
        abort_unless(in_array($format, ['xlsx', 'pdf', 'csv'], true), 404);

        [$columns, $rows] = $this->reports->reportRows($type, $this->filters($request));

        return match ($format) {
            'pdf' => $this->pdf($type, $columns, $rows),
            'csv' => $this->csv($type, $columns, $rows),
            default => $this->xlsx($type, $columns, $rows),
        };
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'event' => ['nullable', 'integer'], 'category' => ['nullable', 'integer'],
            'person' => ['nullable', 'integer'], 'paymentMethod' => ['nullable', 'integer'],
            'direction' => ['nullable', 'in:payable,receivable'],
        ]);
    }

    private function xlsx(string $type, array $columns, array $rows)
    {
        return response()->streamDownload(function () use ($columns, $rows) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($columns));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(fn ($c) => $c ?? '', $row)));
            }
            $writer->close();
        }, "financeiro-{$type}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function csv(string $type, array $columns, array $rows)
    {
        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, "financeiro-{$type}.csv", ['Content-Type' => 'text/csv']);
    }

    private function pdf(string $type, array $columns, array $rows)
    {
        $html = view('reports.financial', compact('type', 'columns', 'rows'))->render();

        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->download("financeiro-{$type}.pdf");
    }
}
