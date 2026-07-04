<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\ReportService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DashboardController extends Controller
{
    public function show(ReportService $reports)
    {
        // Single-event no MVP: o evento gerenciado é o primeiro (padrão do painel)
        $event = Event::query()->orderBy('id')->first();

        if ($event === null) {
            throw new NotFoundHttpException('Nenhum evento cadastrado.');
        }

        return ApiResponse::data($reports->dashboard($event));
    }
}
