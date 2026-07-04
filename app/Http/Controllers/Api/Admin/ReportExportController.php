<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\ReportExportService;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportExportController extends Controller
{
    public function __construct(private readonly ReportExportService $exports)
    {
    }

    public function attendees()
    {
        return $this->exports->attendees($this->event());
    }

    public function attendance()
    {
        return $this->exports->attendance($this->event());
    }

    private function event(): Event
    {
        $event = Event::query()->orderBy('id')->first();

        if ($event === null) {
            throw new NotFoundHttpException('Nenhum evento cadastrado.');
        }

        return $event;
    }
}
