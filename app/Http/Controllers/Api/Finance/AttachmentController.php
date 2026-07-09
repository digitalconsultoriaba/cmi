<?php

namespace App\Http\Controllers\Api\Finance;

use App\Domain\Events\Models\FinancialAttachment;
use App\Domain\Events\Models\FinancialEntry;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function store(Request $request, FinancialEntry $entry)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpeg,png,webp', 'max:8192'],
            'kind' => ['nullable', 'in:receipt,invoice,contract,boleto,other'],
        ]);
        // Disco privado (storage/app/private): comprovantes/NF/contratos/boletos
        // só saem pela rota autenticada abaixo, nunca por URL pública.
        $path = $request->file('file')->store('financial', 'local');
        $att = $entry->attachments()->create([
            'path' => $path, 'kind' => $data['kind'] ?? 'other',
            'original_name' => $request->file('file')->getClientOriginalName(),
            'uploaded_by' => $request->user()->id,
        ]);

        activity('financial.attached')->performedOn($entry)->causedBy($request->user())
            ->withProperties(['reference' => 'FIN-'.$entry->id, 'name' => $att->original_name])
            ->log('Anexo incluído: '.$att->original_name);

        return ApiResponse::data(['id' => $att->id, 'name' => $att->original_name, 'kind' => $att->kind], 201);
    }

    public function download(FinancialEntry $entry, FinancialAttachment $attachment)
    {
        abort_unless($attachment->entry_id === $entry->id, 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Request $request, FinancialEntry $entry, FinancialAttachment $attachment)
    {
        abort_unless($attachment->entry_id === $entry->id, 404);
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        activity('financial.attachment_removed')->performedOn($entry)->causedBy($request->user())
            ->withProperties(['reference' => 'FIN-'.$entry->id])->log('Anexo removido');

        return ApiResponse::data(null);
    }
}
