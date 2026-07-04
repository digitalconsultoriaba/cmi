<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\SupportCase;
use App\Http\Controllers\Controller;
use App\Http\Resources\SupportCaseResource;
use Illuminate\Http\Request;

/**
 * Fila da organização (admin + tesouraria): vê tudo, inclusive notas internas.
 */
class SupportQueueController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportCase::query()
            ->with(['order', 'ticket', 'user'])
            ->latest('updated_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return response()->json([
            'data' => $query->get()
                ->map(fn (SupportCase $case) => SupportCaseResource::forStaff($case)->toArray($request))
                ->values(),
        ]);
    }

    public function show(Request $request, SupportCase $supportCase)
    {
        return response()->json([
            'data' => SupportCaseResource::forStaff(
                $supportCase->load(['notes.author', 'order', 'ticket', 'user'])
            )->toArray($request),
        ]);
    }

    public function addNote(Request $request, SupportCase $supportCase)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'visible_to_attendee' => ['required', 'boolean'],
        ]);

        $supportCase->notes()->create([
            'author_user_id' => $request->user()->id,
            'body' => $data['message'],
            'visible_to_attendee' => $data['visible_to_attendee'],
            'from_attendee' => false,
        ]);

        return $this->show($request, $supportCase->fresh());
    }

    public function finish(Request $request, SupportCase $supportCase)
    {
        $supportCase->forceFill(['status' => 'finished'])->save();

        return $this->show($request, $supportCase->fresh());
    }

    public function reopen(Request $request, SupportCase $supportCase)
    {
        $supportCase->forceFill(['status' => 'reopened'])->save();

        return $this->show($request, $supportCase->fresh());
    }
}
